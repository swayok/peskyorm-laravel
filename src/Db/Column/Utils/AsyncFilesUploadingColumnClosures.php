<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyCMF\Scaffold\Form\UploadedTempFileInfo;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\RecordValueHelpers;
use PeskyORMLaravel\Db\Column\AsyncFilesColumn;
use PeskyORMLaravel\Db\Column\FilesColumn;
use PeskyORMLaravel\Db\Column\ImagesColumn;

class AsyncFilesUploadingColumnClosures extends DefaultColumnClosures {

    public static function valueSetter($newValue, bool $isFromDb, RecordValue $valueContainer, bool $trustDataReceivedFromDb): RecordValue
    {
        if ($isFromDb) {
            return parent::valueSetter($newValue, $isFromDb, $valueContainer, $trustDataReceivedFromDb);
        }
        /** @var AsyncFilesColumn $column */
        $column = $valueContainer->getColumn();
        $normaizledValue = static::valueNormalizer($newValue, false, $column);
        $errors = $column->validateValue($normaizledValue, false, false);
        if (count($errors) > 0) {
            return $valueContainer->setValidationErrors($errors);
        }
        /** @var array $newValue */
        [$newFiles, $filesToDelete, $updatedValue] = static::collectDataForSaving($normaizledValue, $valueContainer);
        $valueContainer->setIsFromDb(false);
        $json = json_encode($updatedValue, JSON_UNESCAPED_UNICODE);
        $valueContainer
            ->setRawValue($updatedValue, $json, false)
            ->setValidValue($json, $updatedValue);
        if (!empty($newFiles) || !empty($filesToDelete)) {
            $valueContainer->setDataForSavingExtender(['new' => $newFiles, 'delete' => $filesToDelete]);
        }
        return $valueContainer;
    }

    /**
     * @param array $normaizledValue
     * @param RecordValue $valueContainer
     * @return array
     */
    static protected function collectDataForSaving(array $normaizledValue, RecordValue $valueContainer) {
        /** @var FilesColumn $column */
        $column = $valueContainer->getColumn();
        $filesGroups = $column->getFilesGroupsConfigurations();

        $currentValue = [];
        if ($valueContainer->hasValue()) {
            $value = static::valueFormatter($valueContainer, 'array');
            $record = $valueContainer->getRecord();
            foreach ($value as $filesGroupName => $files) {
                if (!isset($filesGroups[$filesGroupName])) {
                    continue;
                }
                $filesGroup = $filesGroups[$filesGroupName];
                foreach ($files as $fileInfoArray) {
                    $fileInfo = FileInfo::fromArray($fileInfoArray, $filesGroup, $record);
                    $currentValue[$filesGroupName][$fileInfo->getUuid()] = $fileInfo;
                }
            }
        }

        $newFiles = [];
        $filesToDelete = [];
        $updatedValue = [];

        foreach ($filesGroups as $filesGroupName => $fileConfig) {
            $notDeletedUuids = [];
            $newValuesForGroup = array_get($normaizledValue, $filesGroupName) ?: [];
            /** @var FileInfo[] $currentValuesForGroup */
            $currentValuesForGroup = array_get($currentValue, $filesGroupName) ?: [];
            foreach ($newValuesForGroup as $index => $newValue) {
                if ($newValue instanceof UploadedTempFileInfo) {
                    // new file
                    $fileInfo = $newValue->toFileInfo($fileConfig, $valueContainer->getRecord(), static::makeSuffix($index));
                    $updatedValue[$filesGroupName][] = $fileInfo->collectFileInfoForDb();
                    $newFiles[$filesGroupName][] = $fileInfo;
                } else if (isset($newValue['uuid'], $currentValuesForGroup[$newValue['uuid']])) {
                    // existing file
                    $notDeletedUuids[$newValue['uuid']] = $newValue['uuid'];
                    $updatedValue[$filesGroupName][] = $currentValuesForGroup[$newValue['uuid']]->collectFileInfoForDb();
                }
            }
            if (empty($updatedValue[$filesGroupName])) {
                $updatedValue[$filesGroupName] = [];
            }
            $deletedFiles = array_diff_key($currentValuesForGroup, $notDeletedUuids);
            if (count($deletedFiles) > 0) {
                $filesToDelete[$filesGroupName] = $deletedFiles;
            }
        }

        return [$newFiles, $filesToDelete, $updatedValue];
    }

    /**
     * @param array $fileInfoArray
     * @param RecordValue $valueContainer
     * @param $filesGroupName
     * @return mixed
     */
    static protected function getFileUuid($filesGroupName, array $fileInfoArray, RecordValue $valueContainer) {
        return array_get($fileInfoArray, 'uuid', function () use ($filesGroupName, $fileInfoArray, $valueContainer) {
            /** @var FilesColumn $column */
            $column = $valueContainer->getColumn();
            return FileInfo::fromArray(
                    $fileInfoArray,
                    $column->getFilesGroupConfiguration($filesGroupName),
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
     */
    public static function valueNormalizer($value, $isFromDb, Column $column) {
        if ($isFromDb && !is_array($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value) || empty($value)) {
            return [];
        }
        $filesGroups = $column->getFilesGroupsConfigurations();
        /** @var FilesGroupConfig $fileConfig */
        foreach ($filesGroups as $fileGroupName => $fileConfig) {
            if (empty($value[$fileGroupName])) {
                unset($value[$fileGroupName]);
                continue;
            }

            if ($isFromDb) {
                if (!is_array($value[$fileGroupName])) {
                    unset($value[$fileGroupName]);
                    continue;
                }
                $value[$fileGroupName] = static::normalizeDbValueForGroup($value[$fileGroupName]);
            } else {
                $value[$fileGroupName] = static::normalizeUploadedFilesGroup($value[$fileGroupName]);
            }

            if (empty($value[$fileGroupName]) || !is_array($value[$fileGroupName])) {
                unset($value[$fileGroupName]);
            }
        }
        return array_intersect_key($value, $filesGroups);
    }

    /**
     * @param array $existingFiles
     * @return array
     */
    static protected function normalizeDbValueForGroup(array $existingFiles) {
        if (static::isFileInfoArray($existingFiles)) {
            $existingFiles = [$existingFiles];
        }
        return $existingFiles;
    }

    /**
     * @param array $uploadedFiles
     * @return array
     */
    static protected function normalizeUploadedFilesGroup(array $uploadedFiles) {
        $normailzedData = [];
        foreach ($uploadedFiles as $idx => $fileInfo) {
            if (is_string($fileInfo) && strlen($fileInfo) > 3) {
                /** @noinspection SubStrUsedAsStrPosInspection */
                if ($fileInfo[0] === '{') {
                    // FileInfo array
                    $fileInfoArray = json_decode($fileInfo, true);
                    if (!static::isFileInfoArray($fileInfoArray)) {
                        continue;
                    }
                    $normailzedData[] = $fileInfoArray;
                } else if (preg_match('%^uuid:(.*)$%', $fileInfo, $matches)) {
                    // existing file UUID
                    $normailzedData[] = [
                        'uuid' => $matches[1]
                    ];
                } else {
                    $normailzedData[] = new UploadedTempFileInfo($fileInfo, false);
                }
            } else if ($fileInfo instanceof UploadedTempFileInfo || static::isFileInfoArray($fileInfo) || array_has($fileInfo, 'uuid')) {
                $normailzedData[] = $fileInfo;
            }
        }
        return $normailzedData;
    }

    /**
     * Validates value. Uses valueValidatorExtender
     * @param mixed $value
     * @param bool $isFromDb
     * @param bool $isForCondition
     * @param Column|ImagesColumn|FilesColumn $column
     * @return array
     */
    public static function valueValidator($value, $isFromDb, $isForCondition, Column $column) {
        if ($isFromDb || is_string($value)) {
            return parent::valueValidator($value, $isFromDb, $isForCondition, $column);
        }
        $localizations = $column::getValidationErrorsMessages();
        if (!is_array($value)) {
            return [RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_ARRAY)];
        }
        $value = static::valueNormalizer($value, false, $column);
        $errors = [];
        $filesGroups = $column->getFilesGroupsConfigurations();
        foreach ($filesGroups as $filesGroupName => $fileConfig) {
            if (empty($value[$filesGroupName])) {
                continue;
            }
            foreach ($value[$filesGroupName] as $idx => $fileUploadOrFileInfo) {
                if (is_array($fileUploadOrFileInfo)) {
                    // existing file info
                    continue;
                }
                static::validateUploadedFileContents($column, $fileConfig, $fileUploadOrFileInfo, $idx, $errors);
            }
        }
        return $errors;
    }

    /**
     * Validate uploaded file contents (mime type, size, etc.)
     * @param Column|FilesColumn|ImagesColumn $column
     * @param FileConfigInterface $fileConfig
     * @param UploadedTempFileInfo $fileInfo
     * @param int $fileIndex
     * @param array $errors
     * @return bool
     */
    static protected function validateUploadedFileContents(
        Column $column,
        FileConfigInterface $fileConfig,
        UploadedTempFileInfo $fileInfo,
        $fileIndex,
        array &$errors
    ) {
        $localizations = $column::getValidationErrorsMessages();
        $filesGroupName = $fileConfig->getName();
        $errorsKey = $filesGroupName . '.' . $fileIndex;

        if (!$fileInfo->isValid()) {
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage(
                    $localizations,
                    $column::DATA_IS_NOT_A_VALID_UPLOAD_INFO
                ),
                $filesGroupName
            );
        }

        $mimeType = $fileInfo->getType();
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
        } else if ($fileInfo->getSize() / 1024 > $fileConfig->getMaxFileSize()) {
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::FILE_SIZE_IS_TOO_LARGE),
                $filesGroupName,
                $fileConfig->getMaxFileSize()
            );
        }
        return !empty($errors[$errorsKey]);
    }

    /**
     * @param array $value
     * @return bool
     */
    public static function isFileInfoArray(array $value) {
        return !empty($value['name']) && !empty($value['extension']);
    }

    /**
     * Additional actions after value saving to DB (or instead of saving if column does not exist in DB)
     * @param RecordValue $valueContainer
     * @param bool $isUpdate
     * @param array $savedData
     * @return void
     */
    public static function valueSavingExtender(RecordValue $valueContainer, $isUpdate, array $savedData) {
        $updates = $valueContainer->pullDataForSavingExtender();
        if (empty($updates)) {
            // do not remove! infinite recursion will happen!
            return;
        }

        /** @var AsyncFilesColumn $column */
        $column = $valueContainer->getColumn();
        $record = $valueContainer->getRecord();
        $configGroups = $column->getFilesGroupsConfigurations();

        // delete files
        $deletedFiles = (array)array_get($updates, 'delete', []);
        if (!empty($deletedFiles)) {
            /** @var FileInfo[] $files */
            foreach ($deletedFiles as $filesGroupName => $files) {
                foreach ((array)$files as $fileInfo) {
                    static::deleteExistingFile($fileInfo);
                }
            }
        }

        // cleanup empty dirs
        $currentValue = static::valueFormatter($valueContainer, 'array');
        foreach ($configGroups as $filesGroupName => $filesGroupConfig) {
            if (empty($currentValue[$filesGroupName])) {
                \File::cleanDirectory($filesGroupConfig->getAbsolutePathToFileFolder($record));
            }
        }

        // save new files
        $newFiles = (array)array_get($updates, 'new', []);
        if (!empty($newFiles)) {
            /** @var FileInfo[] $fileUploads */
            foreach ($newFiles as $filesGroupName => $fileUploads) {
                $newValue[$filesGroupName] = static::storeUploadedFiles(
                    $record,
                    $configGroups[$filesGroupName],
                    $fileUploads
                );
            }
        }
    }

    /**
     * @param FileInfo $fileInfo
     */
    static protected function deleteExistingFile(FileInfo $fileInfo) {
        \File::delete($fileInfo->getAbsoluteFilePath());
    }

    /**
     * @param RecordInterface $record
     * @param FileConfigInterface $fileConfig
     * @param FileInfo[] $fileUploads
     */
    static protected function storeUploadedFiles(
        RecordInterface $record,
        FileConfigInterface $fileConfig,
        array $fileUploads
    ) {
        $dir = $fileConfig->getAbsolutePathToFileFolder($record);
        if ($fileConfig->getMaxFilesCount() === 1) {
            \File::cleanDirectory($dir);
        }
        if (!\File::isDirectory($dir)) {
            \File::makeDirectory($dir, 0777, true);
        }
        foreach ($fileUploads as $fileInfo) {
            // save not modified file to $dir
            \File::copy($fileInfo->getUploadedFilePath(), $dir . $fileInfo->getFileNameWithExtension());
            // modify file
            static::modifyUploadedFileAfterSaveToFs($fileInfo, $fileConfig);
        }
    }

    static protected function makeSuffix(string $fileIndex): string {
        return time() . $fileIndex;
    }

    /**
     * Modify uploaded file after it was stroed to file system but before data was saved to DB.
     * You can store additional info via $fileInfo->setCustomInfo() (you may need to merge with existing info)
     * @param FileInfo $fileInfo
     * @param FileConfigInterface $fileConfig
     */
    static protected function modifyUploadedFileAfterSaveToFs(FileInfo $fileInfo, FileConfigInterface $fileConfig) {

    }

    /**
     * Additional actions after record deleted from DB
     * @param RecordValue $valueContainer
     * @param bool $deleteFiles
     * @return void
     */
    public static function valueDeleteExtender(RecordValue $valueContainer, $deleteFiles) {
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
     */
    public static function valueFormatter(RecordValue $valueContainer, $format) {
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
                    $value = static::valueFormatter($valueContainer, 'array');
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

    /**
     * @param FilesColumn|Column $column
     * @param array $additionalFormats
     * @return array
     */
    public static function getValueFormats(Column $column, array $additionalFormats = []) {
        $defaultFormats = parent::getValueFormats($column, $additionalFormats);
        $formats = [];
        if ($column instanceof FilesColumn) {
            $formats = [
                'file_info_arrays',
                'urls',
                'urls_with_timestamp',
                'paths'
            ];
            foreach ($column->getFilesGroupsConfigurations() as $groupName => $_) {
                $formats[] = $groupName;
            }
        }
        return array_unique(array_merge($defaultFormats, $formats));
    }
}
