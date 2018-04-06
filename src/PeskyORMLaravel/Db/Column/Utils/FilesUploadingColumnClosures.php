<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\RecordValueHelpers;
use PeskyORMLaravel\Db\Column\FilesColumn;
use PeskyORMLaravel\Db\Column\ImagesColumn;
use Swayok\Utils\ValidateValue;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class FilesUploadingColumnClosures extends DefaultColumnClosures {

    /**
     * Set value. Should also normalize and validate value
     * @param mixed $newValue
     * @param boolean $isFromDb
     * @param RecordValue $valueContainer
     * @param bool $trustDataReceivedFromDb
     * @return RecordValue
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function valueSetter($newValue, $isFromDb, RecordValue $valueContainer, $trustDataReceivedFromDb) {
        if ($isFromDb || empty($newValue)) {
            return parent::valueSetter($newValue, $isFromDb, $valueContainer, $trustDataReceivedFromDb);
        }
        /** @var FilesColumn $column */
        $column = $valueContainer->getColumn();
        $errors = $column->validateValue($newValue, $isFromDb);
        if (count($errors) > 0) {
            return $valueContainer->setValidationErrors($errors);
        }
        /** @var array $newValue */
        $normaizledValue = static::valueNormalizer($newValue, $isFromDb, $column);
        if (count($normaizledValue) === 0) {
            if ($valueContainer->hasValue()) {
                $valueContainer->setDataForSavingExtender(['new' => [], 'delete' => $valueContainer->getValue()]);
            }
            $valueContainer->setRawValue('{}', '{}', false)->setValidValue('{}', '{}');
        } else {
            list($newFiles, $filesToDelete, $updatedValue) = static::collectDataForSaving($normaizledValue, $valueContainer);
            $valueContainer->setIsFromDb(false);
            $json = json_encode($updatedValue, JSON_UNESCAPED_UNICODE);
            $valueContainer
                ->setRawValue($updatedValue, $json, false)
                ->setValidValue($json, $updatedValue);
            if (!empty($newFiles) || !empty($filesToDelete)) {
                $valueContainer->setDataForSavingExtender(['new' => $newFiles, 'delete' => $filesToDelete]);
            }
        }
        return $valueContainer;
    }

    /**
     * @param array $normaizledValue
     * @param RecordValue $valueContainer
     * @return array
     * @throws \BadMethodCallException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \UnexpectedValueException
     */
    static protected function collectDataForSaving(array $normaizledValue, RecordValue $valueContainer) {
        list($newFiles, $uuidPosition, $uuidsOfFilesToDelete) = static::analyzeUploadedFilesAndData($normaizledValue);

        /** @var FilesColumn $column */
        $column = $valueContainer->getColumn();
        $currentValue = [];
        if ($valueContainer->hasValue()) {
            $currentValue = $valueContainer->getValue();
            if (!is_array($currentValue)) {
                $currentValue = json_decode($currentValue, true);
                if (is_array($currentValue) && !empty($currentValue)) {
                    $currentValue = static::valueNormalizer($currentValue, false, $column);
                } else {
                    $currentValue = [];
                }
            }
        }


        $filesToDelete = static::getExitstingFilesToDeleteOrUpdatePositions(
            $currentValue,
            $valueContainer,
            $uuidPosition,
            $uuidsOfFilesToDelete
        );

        foreach ($column->getFilesGroupsConfigurations() as $filesGroupName => $fileConfig) {
            $fileInfosForGroup = array_get($normaizledValue, $filesGroupName, []);
            foreach ($fileInfosForGroup as $fileInfo) {
                if (static::isFileInfoArray($fileInfo)) {
                    $currentValue[$filesGroupName][] = $fileInfo;
                }
            }
            if (!empty($currentValue[$filesGroupName])) {
                $currentValue[$filesGroupName] = static::reorderGroupOfFiles($currentValue[$filesGroupName]);
            }
        }

        return [$newFiles, $filesToDelete, $currentValue];
    }

    /**
     * @param array $normaizledValue
     * @return array
     * @throws \UnexpectedValueException
     */
    static protected function analyzeUploadedFilesAndData(array $normaizledValue) {
        $newFiles = [];
        $uuidsOfFilesToDelete = [];
        $uuidPosition = [];
        foreach ($normaizledValue as $filesGroupName => $files) {
            foreach ($files as $index => $fileInfo) {
                if (array_key_exists('file', $fileInfo)) {
                    $fileInfo['deleted'] = true;
                    $newFiles[$filesGroupName][] = $fileInfo;
                }
                if (!empty($fileInfo['uuid'])) {
                    if (array_get($fileInfo, 'deleted', false)) {
                        $uuidsOfFilesToDelete[$filesGroupName][] = $fileInfo['uuid'];
                    } else {
                        $uuidPosition[$filesGroupName][$fileInfo['uuid']] = array_get($fileInfo, 'position', time() + (int)$index);
                    }
                }
            }
        }
        return [$newFiles, $uuidPosition, $uuidsOfFilesToDelete];
    }

    /**
     * @param array $currentValue
     * @param RecordValue $valueContainer
     * @param array $uuidPosition
     * @param array $uuidsOfFilesToDelete
     * @return array
     * @throws \UnexpectedValueException
     */
    static protected function getExitstingFilesToDeleteOrUpdatePositions(
        array &$currentValue,
        RecordValue $valueContainer,
        array $uuidPosition,
        array $uuidsOfFilesToDelete
    ) {
        $filesToDelete = [];
        if (!empty($currentValue)) {
            foreach ($currentValue as $filesGroupName => $existingFiles) {
                $removedIndexes = [];
                foreach ((array)$existingFiles as $index => $fileData) {
                    $uuid = static::getFileUuid($filesGroupName, $fileData, $valueContainer);
                    if (in_array($uuid, array_get($uuidsOfFilesToDelete, $filesGroupName, []), true)) {
                        $filesToDelete[$filesGroupName][] = $fileData;
                        $removedIndexes[] = $index;
                    } else if (array_has($uuidPosition, "{$filesGroupName}.{$uuid}")) {
                        $currentValue[$filesGroupName][$index]['position'] = $uuidPosition[$filesGroupName][$uuid];
                    }
                }
                foreach ($removedIndexes as $index) {
                    unset($currentValue[$filesGroupName][$index]);
                }
            }
        }
        return $filesToDelete;
    }

    /**
     * @param array $fileData
     * @param RecordValue $valueContainer
     * @param $fileName
     * @return mixed
     * @throws \UnexpectedValueException
     */
    static protected function getFileUuid($fileName, array $fileData, RecordValue $valueContainer) {
        return array_get($fileData, 'uuid', function () use ($fileName, $fileData, $valueContainer) {
            /** @var FilesColumn $column */
            $column = $valueContainer->getColumn();
            return FileInfo::fromArray(
                    $fileData,
                    $column->getFilesGroupConfiguration($fileName),
                    $valueContainer->getRecord()
                )
                ->getUuid();
        });
    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column|ImagesColumn|FilesColumn $column
     * @return array
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    static public function valueNormalizer($value, $isFromDb, Column $column) {
        if ($isFromDb && !is_array($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }
        $filesGroups = [];
        /** @var FilesGroupConfig $fileConfig */
        foreach ($column->getFilesGroupsConfigurations() as $fileGroupName => $fileConfig) {
            $filesGroups[] = $fileGroupName;
            if (empty($value[$fileGroupName])) {
                unset($value[$fileGroupName]);
                continue;
            }

            if ($isFromDb) {
                if (!is_array($value[$fileGroupName])) {
                    unset($value[$fileGroupName]);
                    continue;
                }
                $value[$fileGroupName] = static::normalizeDbValue($value[$fileGroupName]);
            } else {
                if ($value[$fileGroupName] instanceof \SplFileInfo) {
                    $value[$fileGroupName] = $isFromDb ? [] : [['file' => $value[$fileGroupName]]];
                }
                if (!is_array($value[$fileGroupName])) {
                    unset($value[$fileGroupName]);
                    continue;
                }
                $value[$fileGroupName] = static::normalizeUploadedFiles($value[$fileGroupName]);
            }

            if (empty($value[$fileGroupName]) || !is_array($value[$fileGroupName])) {
                unset($value[$fileGroupName]);
            }
        }
        return array_intersect_key($value, array_flip($filesGroups));
    }

    /**
     * @param array $existingFiles
     * @return array
     */
    static protected function normalizeDbValue(array $existingFiles) {
        if (static::isFileInfoArray($existingFiles)) {
            $existingFiles = [$existingFiles];
        }
        return $existingFiles;
    }

    /**
     * @param array $uploadedFiles
     * @return array
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    static protected function normalizeUploadedFiles(array $uploadedFiles) {
        if (array_has($uploadedFiles, 'file') || array_has($uploadedFiles, 'deleted')) {
            // normalize uploaded file info to be indexed array with file uploads inside
            $uploadedFiles = [$uploadedFiles];
        }
        $normailzedData = [];
        foreach ($uploadedFiles as $idx => $fileUploadInfo) {
            if (!ValidateValue::isInteger($idx)) {
                continue; //< this is not expected here -> ignore
            } else if ($fileUploadInfo instanceof \SplFileInfo) {
                $normailzedData[] = [
                    'file' => $fileUploadInfo
                ];
            } else if (static::isFileInfoArray($fileUploadInfo)) {
                // file info array is being saved to DB via static::valueSavingExtender() or manually
                unset($fileUploadInfo['deleted']);
                $normailzedData[] = $fileUploadInfo;
            } else {
                $fileUploadInfo['deleted'] = (bool)array_get($fileUploadInfo, 'deleted', false);
                if (!empty($fileUploadInfo['file'])) {
                    // new file uploaded
                    $normailzedData[] = $fileUploadInfo;
                } else if (!empty($fileUploadInfo['file_data'])) {
                    // new file uploaded as base64 encoded data
                    $base64FileInfo = json_decode($fileUploadInfo['file_data'], true);
                    if (is_array($base64FileInfo) && array_has($base64FileInfo, ['data', 'name', 'extension'])) {
                        $fileUploadInfo = [
                            'file' => new Base64UploadedFile(
                                $base64FileInfo['data'],
                                rtrim($base64FileInfo['name'] . '.' . $base64FileInfo['extension'], '.')
                            )
                        ];
                        if (array_has($base64FileInfo, 'uuid')) {
                            $fileUploadInfo['uuid'] = $base64FileInfo['uuid'];
                        }
                        $normailzedData[] = $fileUploadInfo;
                    }
                } else if (array_has($fileUploadInfo, 'uuid')) {
                    if ((bool)array_get($fileUploadInfo, 'deleted', false)) {
                        // old file deleted while new one is not provided
                        $fileUploadInfo['deleted'] = true;
                        unset($fileUploadInfo['file'], $fileUploadInfo['file_data']);
                        $normailzedData[] = $fileUploadInfo;
                    } else if (array_has($fileUploadInfo, 'position')) {
                        // file already exists but may have changed position
                        $normailzedData[] = [
                            'uuid' => $fileUploadInfo['uuid'],
                            'position' => $fileUploadInfo['position'],
                            'deleted' => false
                        ];
                    }
                }
                // ignore any other case
            }
        }
        return $normailzedData;
    }

    /**
     * Validates value. Uses valueValidatorExtender
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column|ImagesColumn|FilesColumn $column
     * @return array
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function valueValidator($value, $isFromDb, Column $column) {
        if ($isFromDb || is_string($value)) {
            return parent::valueValidator($value, $isFromDb, $column);
        }
        $localizations = $column::getValidationErrorsMessages();
        if (!is_array($value)) {
            return [RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_ARRAY)];
        }
        $value = static::valueNormalizer($value, $isFromDb, $column);
        $errors = [];
        /** @noinspection ForeachSourceInspection */
        foreach ($column as $filesGroupName => $fileConfig) {
            if (!array_key_exists($filesGroupName, $value)) {
                continue;
            }
            foreach ($value[$filesGroupName] as $idx => $fileUploadOrFileInfo) {
                if (static::isFileInfoArray($fileUploadOrFileInfo)) {
                    continue;
                }
                /** @var bool|\SplFileInfo|array $file */
                $file = array_get($fileUploadOrFileInfo, 'file') ?: false;
                $isUploadedFile = $file && ValidateValue::isUploadedFile($file, true);
                $errorsKey = $filesGroupName . '.' . $idx;

                if (
                    !$isUploadedFile
                    && !array_get($fileUploadOrFileInfo, 'deleted', false)
                    && empty($fileUploadOrFileInfo['uuid'])
                ) {
                    $errors[$errorsKey][] = sprintf(
                        RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_FILE),
                        $filesGroupName
                    );
                }
                if (!$isUploadedFile || !empty($errors[$errorsKey])) {
                    // old file present or only file deletion requested
                    continue;
                }
                if (is_array($file)) {
                    $file = static::makeUploadedFileFromArray($file);
                } else if (!($file instanceof SymfonyUploadedFile) && ($file instanceof \SplFileInfo)) {
                    $file = static::makeUploadedFileFromSplFileInfo($file);
                }
                static::validateUploadedFileContents($column, $fileConfig, $file, $idx, $errors);
            }
        }
        return $errors;
    }

    /**
     * Validate uploaded file contents (mime type, size, etc.)
     * @param Column|FilesColumn|ImagesColumn $column
     * @param FilesGroupConfig $fileConfig
     * @param SymfonyUploadedFile $file
     * @param int $fileIndex
     * @param array $errors
     * @return bool
     */
    static protected function validateUploadedFileContents(
        Column $column,
        FilesGroupConfig $fileConfig,
        SymfonyUploadedFile $file,
        $fileIndex,
        array &$errors
    ) {
        $localizations = $column::getValidationErrorsMessages();
        $filesGroupName = $fileConfig->getName();
        $errorsKey = $filesGroupName . '.' . $fileIndex;

        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
        if (!in_array($mimeType, $fileConfig->getAllowedMimeTypes(), true)) {
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage(
                    $localizations,
                    $column->isItAnImage() ? $column::IMAGE_TYPE_IS_NOT_ALLOWED : $column::FILE_TYPE_IS_NOT_ALLOWED
                ),
                $mimeType,
                $filesGroupName,
                implode(', ', $fileConfig->getAllowedMimeTypes())
            );
        } else if ($file->getSize() / 1024 > $fileConfig->getMaxFileSize()) {
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::FILE_SIZE_IS_TOO_LARGE),
                $filesGroupName,
                $fileConfig->getMaxFileSize()
            );
        }
        return !empty($errors[$errorsKey]);
    }

    /**
     * @param array $fileUpload
     * @return \Illuminate\Http\UploadedFile
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    static protected function makeUploadedFileFromArray(array $fileUpload) {
        return new \Illuminate\Http\UploadedFile(
            $fileUpload['tmp_name'],
            array_get($fileUpload, 'name'),
            array_get($fileUpload, 'type'),
            array_get($fileUpload, 'size'),
            array_get($fileUpload, 'error'),
            true
        );
    }

    /**
     * @param \SplFileInfo $fileInfo
     * @return \Illuminate\Http\UploadedFile
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    static protected function makeUploadedFileFromSplFileInfo(\SplFileInfo $fileInfo) {
        return new \Illuminate\Http\UploadedFile(
            $fileInfo->getFilename(),
            $fileInfo->getFilename(),
            null,
            $fileInfo->getSize(),
            null,
            true
        );
    }

    /**
     * @param array $value
     * @return bool
     */
    static public function isFileInfoArray(array $value) {
        return !empty($value['name']) && !empty($value['extension']);
    }

    /**
     * Additional actions after value saving to DB (or instead of saving if column does not exist in DB)
     * @param RecordValue $valueContainer
     * @param bool $isUpdate
     * @param array $savedData
     * @return void
     * @throws \PeskyORM\Exception\InvalidTableColumnConfigException
     * @throws \PeskyORM\Exception\InvalidDataException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    static public function valueSavingExtender(RecordValue $valueContainer, $isUpdate, array $savedData) {
        $updates = $valueContainer->pullDataForSavingExtender();
        if (empty($updates)) {
            // do not remove! infinite recursion will happen!
            return;
        }

        /** @var FilesColumn $column */
        $column = $valueContainer->getColumn();
        $record = $valueContainer->getRecord();
        $deletedFiles = (array)array_get($updates, 'delete', []);
        foreach ($deletedFiles as $filesGroupName => $files) {
            $fileConfig = $column->getFilesGroupConfiguration($filesGroupName);
            foreach ((array)$files as $fileInfo) {
                $existingFileInfo = FileInfo::fromArray($fileInfo, $fileConfig, $record);
                static::deleteExistingFiles($existingFileInfo);
            }
        }

        // cleanup files that no longer exist in file system and their modifications (in case of images)
        $newValue = $valueContainer->getValue();
        if (is_string($newValue)) {
            $newValue = json_decode($valueContainer->getValue(), true) ?: [];
        }
        foreach ($newValue as $filesGroupName => $existingFiles) {
            $indexesToRemove = [];
            $filesGroupConfig = $column->getFilesGroupConfiguration($filesGroupName);
            foreach ((array)$existingFiles as $index => $fileInfo) {
                if (!static::isFileInfoArray($fileInfo)) {
                    $indexesToRemove[] = $index;
                } else {
                    $fileInfo = FileInfo::fromArray($fileInfo, $filesGroupConfig, $record);
                    if (!$fileInfo->exists()) {
                        static::deleteExistingFiles($fileInfo); //< to make sure there is no file and its modification remain
                        $indexesToRemove[] = $index;
                    }
                }
            }
            foreach ($indexesToRemove as $index) {
                unset($newValue[$filesGroupName][$index]);
            }
            $newValue[$filesGroupName] = static::limitFilesCount($newValue[$filesGroupName], $filesGroupConfig, $record);
        }

        $newFiles = (array)array_get($updates, 'new', []);
        if (!empty($newFiles)) {
            /** @var array $fileUploads */
            foreach ($newFiles as $filesGroupName => $fileUploads) {
                if (empty($newValue[$filesGroupName])) {
                    $newValue[$filesGroupName] = [];
                }
                $filesGroupConfig = $column->getFilesGroupConfiguration($filesGroupName);
                $newValue[$filesGroupName] = static::storeUploadedFiles(
                    $record,
                    $filesGroupConfig,
                    $fileUploads,
                    $newValue[$filesGroupName]
                );
                if (empty($newValue[$filesGroupName])) {
                    unset($newValue[$filesGroupName]);
                } else {
                    $newValue[$filesGroupName] = static::reorderGroupOfFiles($newValue[$filesGroupName]);
                }
                $newValue[$filesGroupName] = static::limitFilesCount($newValue[$filesGroupName], $filesGroupConfig, $record);
            }
        }

        $record
            ->unsetValue($valueContainer->getColumn()) //< to avoid merging
            ->begin()
            ->updateValue($valueContainer->getColumn(), $newValue, false)
            ->commit();
    }

    /**
     * @param array $filesInfos
     * @param FilesGroupConfig $filesGroupConfig
     * @param RecordInterface $record
     * @return array
     */
    static protected function limitFilesCount(array $filesInfos, FilesGroupConfig $filesGroupConfig, RecordInterface $record) {
        while (count($filesInfos) > $filesGroupConfig->getMaxFilesCount()) {
            static::deleteExistingFiles(FileInfo::fromArray(array_shift($filesInfos), $filesGroupConfig, $record));
        }
        return array_values($filesInfos);
    }

    /**
     * @param FileInfo $fileInfo
     * @throws \UnexpectedValueException
     */
    static protected function deleteExistingFiles(FileInfo $fileInfo) {
        \File::delete($fileInfo->getAbsoluteFilePath());
    }

    /**
     * @param RecordInterface $record
     * @param FilesGroupConfig|ImagesGroupConfig $fileConfig
     * @param array $fileUploads
     * @param array $existingFiles
     * @return array
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \UnexpectedValueException
     */
    static protected function storeUploadedFiles(
        RecordInterface $record,
        FilesGroupConfig $fileConfig,
        array $fileUploads,
        array $existingFiles
    ) {
        $baseSuffix = time();
        $dir = $fileConfig->getAbsolutePathToFileFolder($record);
        if ($fileConfig->getMaxFilesCount() === 1) {
            \File::cleanDirectory($dir);
        }
        if (!\File::isDirectory($dir)) {
            \File::makeDirectory($dir, 0777, true);
        }
        $filesSaved = 0;
        foreach ($fileUploads as $uploadInfo) {
            $file = array_get($uploadInfo, 'file', false);
            if (!empty($file)) {
                $fileInfo = FileInfo::fromSplFileInfo($file, $fileConfig, $record, (string)$baseSuffix . (string)$filesSaved);
                $fileInfo->setPosition(array_get($uploadInfo, 'position', time() + $filesSaved));
                $fileInfo->setCustomInfo(array_get($uploadInfo, 'info', []));
                $filesSaved++;
                // save not modified file to $dir
                $filePath = $file->getRealPath();
                if ($file instanceof SymfonyUploadedFile) {
                    if (is_uploaded_file($filePath)) {
                        $file->move($dir, $fileInfo->getFileNameWithExtension());
                    } else {
                        \File::copy($filePath, $dir . $fileInfo->getFileNameWithExtension());
                    }
                } else {
                    /** @var \SplFileInfo $file */
                    \File::copy($filePath, $dir . $fileInfo->getFileNameWithExtension());
                }
                // modify file
                static::modifyUploadedFileAfterSaveToFs($fileInfo, $fileConfig);
                $existingFiles[] = $fileInfo->collectImageInfoForDb();
            }
        }
        if (empty($existingFiles)) {
            \File::cleanDirectory($dir);
        }
        return $existingFiles;
    }

    /**
     * Modify uploaded file after it was stroed to file system but before data was saved to DB.
     * You can store additional info via $fileInfo->setCustomInfo() (you may need to merge with existing info)
     * @param FileInfo $fileInfo
     * @param FilesGroupConfig $fileConfig
     */
    static protected function modifyUploadedFileAfterSaveToFs(FileInfo $fileInfo, FilesGroupConfig $fileConfig) {

    }

    /**
     * @param array $filesInfos
     * @return array
     */
    static protected function reorderGroupOfFiles(array $filesInfos) {
        usort($filesInfos, function ($item1, $item2) {
            $pos1 = (int)array_get($item1, 'position', time() + 100);
            $pos2 = (int)array_get($item2, 'position', time() + 101);
            if ($pos1 === $pos2) {
                return 0;
            } else {
                return $pos1 > $pos2 ? 1 : -1;
            }
        });
        return array_values($filesInfos);
    }

    /**
     * Additional actions after record deleted from DB
     * @param RecordValue $valueContainer
     * @param bool $deleteFiles
     * @return void
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function valueDeleteExtender(RecordValue $valueContainer, $deleteFiles) {
        if ($deleteFiles) {
            /** @var FilesColumn $column */
            $column = $valueContainer->getColumn();
            $pkValue = $valueContainer->getRecord()->getPrimaryKeyValue();
            foreach ($column as $filesGroupName => $fileConfig) {
                \File::cleanDirectory($fileConfig->getAbsolutePathToFileFolder($valueContainer->getRecord()));
                if ($pkValue) {
                    \File::cleanDirectory($fileConfig->getAbsolutePathToFileFolder($valueContainer->getRecord()));
                }
            }
        }
    }

    /**
     * Formats value according to required $format
     * @param RecordValue $valueContainer
     * @param string $format
     * @return mixed
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function valueFormatter(RecordValue $valueContainer, $format) {
        /** @var FilesColumn $column */
        $column = $valueContainer->getColumn();
        if ($column->hasFilesGroupConfiguration($format)) {
            // colname_as_$groupName where $groupName === $format
            // returns FileInfo[]
            return $valueContainer->getCustomInfo(
                'file_info:' . $format,
                function () use ($valueContainer, $format, $column) {
                    // return FileInfo object or array of FileInfo objects by image config name provided via $format
                    $record = $valueContainer->getRecord();
                    $value = $record->getValue($column->getName(), 'array');
                    $fileConfig = $column->getFilesGroupConfiguration($format);
                    $ret = [];
                    if (!empty($value[$format]) && is_array($value[$format])) {
                        foreach ($value[$format] as $imageInfoArray) {
                            if (static::isFileInfoArray($imageInfoArray)) {
                                $imageInfo = FileInfo::fromArray($imageInfoArray, $fileConfig, $record);
                                if ($imageInfo->exists()) {
                                    $ret[] = $imageInfo;
                                }
                            }
                        }
                        /*for ($i = count($ret); $i < $fileConfig->getMaxFilesCount(); $i++) {
                            $ret[] = NoFileInfo::create();
                        }*/
                    }
                    return $ret;
                },
                true
            );
        } else if ($format === 'file_info_arrays') {
            // colname_as_file_info_arrays
            // returns ['group_name1' => FileInfo[], 'group_name2' => FileInfo[]]
            return $valueContainer->getCustomInfo(
                'file_info:all',
                function () use ($valueContainer, $column) {
                    $ret = [];
                    foreach ($column as $fileConfig) {
                        /** @noinspection AmbiguousMethodsCallsInArrayMappingInspection */
                        $ret[$fileConfig->getName()] = static::valueFormatter($valueContainer, $fileConfig->getName());
                    }
                    return $ret;
                },
                true
            );
        } else if (in_array($format, ['urls', 'urls_with_timestamp', 'paths'], true)) {
            // colname_as_urls / colname_as_urls_with_timestamp / colname_as_paths
            // returns array of strings
            return $valueContainer->getCustomInfo(
                'format:' . $format,
                function () use ($valueContainer, $format, $column) {
                    $value = parent::valueFormatter($valueContainer, 'array');
                    $ret = [];
                    foreach ($value as $filesGroupName => $fileInfo) {
                        if (is_array($fileInfo)) {
                            $fileConfig = $column->getFilesGroupConfiguration($filesGroupName);
                            $ret[$filesGroupName] = [];
                            foreach ($fileInfo as $realFileInfo) {
                                if (static::isFileInfoArray($realFileInfo)) {
                                    $fileInfo = FileInfo::fromArray($realFileInfo, $fileConfig, $valueContainer->getRecord());
                                    if (!$fileInfo->exists()) {
                                        continue;
                                    }
                                    if ($format === 'paths') {
                                        $ret[$filesGroupName][] = $fileInfo->getAbsoluteFilePath();
                                    } else {
                                        $url = $fileInfo->getAbsoluteUrl();
                                        if ($format === 'urls_with_timestamp') {
                                            $url .= '?_' . time();
                                        }
                                        $ret[$filesGroupName][] = $url;
                                    }
                                }
                            }
                        }
                    }
                    return $ret;
                },
                true
            );
        } else {
            return parent::valueFormatter($valueContainer, $format);
        }
    }
}