<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\RecordValueHelpers;
use PeskyORMLaravel\Db\Column\ImagesColumn;
use Swayok\Utils\ValidateValue;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImagesUploadingColumnClosures extends DefaultColumnClosures{

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
        /** @var ImagesColumn $column */
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

        /** @var ImagesColumn $column */
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

        foreach ($column->getImagesConfigurations() as $imageName => $imageConfig) {
            $fileInfosForGroup = array_get($normaizledValue, $imageName, []);
            foreach ($fileInfosForGroup as $fileInfo) {
                if (static::isFileInfoArray($fileInfo)) {
                    $currentValue[$imageName][] = $fileInfo;
                }
            }
            if (!empty($currentValue[$imageName])) {
                $currentValue[$imageName] = static::reorderGroupOfFiles($currentValue[$imageName]);
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
        foreach ($normaizledValue as $imageName => $images) {
            foreach ($images as $index => $imageInfo) {
                if (array_key_exists('file', $imageInfo)) {
                    $imageInfo['deleted'] = true;
                    $newFiles[$imageName][] = $imageInfo;
                }
                if (!empty($imageInfo['uuid'])) {
                    if (array_get($imageInfo, 'deleted', false)) {
                        $uuidsOfFilesToDelete[$imageName][] = $imageInfo['uuid'];
                    } else {
                        $uuidPosition[$imageName][$imageInfo['uuid']] = array_get($imageInfo, 'position', time() + (int)$index);
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
            foreach ($currentValue as $imageName => $existingFiles) {
                $removedIndexes = [];
                foreach ((array)$existingFiles as $index => $fileData) {
                    $uuid = static::getFileUuid($imageName, $fileData, $valueContainer);
                    if (in_array($uuid, array_get($uuidsOfFilesToDelete, $imageName, []), true)) {
                        $filesToDelete[$imageName][] = $fileData;
                        $removedIndexes[] = $index;
                    } else if (array_has($uuidPosition, "{$imageName}.{$uuid}")) {
                        $currentValue[$imageName][$index]['position'] = $uuidPosition[$imageName][$uuid];
                    }
                }
                foreach ($removedIndexes as $index) {
                    unset($currentValue[$imageName][$index]);
                }
            }
        }
        return $filesToDelete;
    }

    /**
     * @param array $imageData
     * @param RecordValue $valueContainer
     * @param $imageName
     * @return mixed
     * @throws \UnexpectedValueException
     */
    static protected function getFileUuid($imageName, array $imageData, RecordValue $valueContainer) {
        return array_get($imageData, 'uuid', function () use ($imageName, $imageData, $valueContainer) {
            /** @var ImagesColumn $column */
            $column = $valueContainer->getColumn();
            return FileInfo::fromArray(
                    $imageData,
                    $column->getImageConfiguration($imageName),
                    $valueContainer->getRecord()
                )
                ->getUuid();
        });
    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column|ImagesColumn $column
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
        $imagesNames = [];
        /** @var ImagesColumn $column */
        /** @var ImageConfig $imageConfig */
        foreach ($column->getImagesConfigurations() as $imageName => $imageConfig) {
            $imagesNames[] = $imageName;
            if (empty($value[$imageName])) {
                unset($value[$imageName]);
                continue;
            }

            if ($isFromDb) {
                if (!is_array($value[$imageName])) {
                    unset($value[$imageName]);
                    continue;
                }
                $value[$imageName] = static::normalizeDbValue($value[$imageName]);
            } else {
                if ($value[$imageName] instanceof \SplFileInfo) {
                    $value[$imageName] = $isFromDb ? [] : [['file' => $value[$imageName]]];
                }
                if (!is_array($value[$imageName])) {
                    unset($value[$imageName]);
                    continue;
                }
                $value[$imageName] = static::normalizeUploadedFiles($value[$imageName]);
            }

            if (empty($value[$imageName]) || !is_array($value[$imageName])) {
                unset($value[$imageName]);
            }
        }
        return array_intersect_key($value, array_flip($imagesNames));
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
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param Column|ImagesColumn $column
     * @return array
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * todo: fix validation for case when all files were deleted while at least 1 file is required
     */
    static public function valueValidator($value, $isFromDb, Column $column) {
        if ($value instanceof RecordValue) {
            $value = $value->getValue();
        }
        if ($isFromDb || is_string($value)) {
            return parent::valueValidator($value, $isFromDb, $column);
        }
        $localizations = $column::getValidationErrorsLocalization();
        if (!is_array($value)) {
            return [RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_ARRAY)];
        }
        $value = static::valueNormalizer($value, $isFromDb, $column);
        $errors = [];
        /** @noinspection ForeachSourceInspection */
        foreach ($column as $imageName => $imageConfig) {
            if (!array_key_exists($imageName, $value)) {
                continue;
            }
            foreach ($value[$imageName] as $idx => $fileUploadOrFileInfo) {
                if (static::isFileInfoArray($fileUploadOrFileInfo)) {
                    continue;
                }
                /** @var bool|\SplFileInfo $file */
                $file = array_get($fileUploadOrFileInfo, 'file', false);
                $isUploadedImage = ValidateValue::isUploadedImage($file, true);
                if (
                    !$isUploadedImage
                    && !array_get($fileUploadOrFileInfo, 'deleted', false)
                    && empty($fileUploadOrFileInfo['uuid'])
                ) {
                    if (!array_key_exists($imageName . '.' . $idx, $errors)) {
                        $errors[$imageName . '.' . $idx] = [];
                    }
                    $errors[$imageName . '.' . $idx][] = sprintf(
                        RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_IMAGE),
                        $imageName
                    );
                }
                if (!$isUploadedImage) {
                    // old file present or only file deletion requested
                    continue;
                }
                $image = new \Imagick($file->getRealPath());
                if (!$image->valid() || ($image->getImageMimeType() === 'image/jpeg' && ValidateValue::isCorruptedJpeg($file->getRealPath()))) {
                    if (!array_key_exists($imageName . '.' . $idx, $errors)) {
                        $errors[$imageName . '.' . $idx] = [];
                    }
                    $errors[$imageName . '.' . $idx][] = sprintf(
                        RecordValueHelpers::getErrorMessage($localizations, $column::FILE_IS_NOT_A_VALID_IMAGE),
                        $imageName
                    );
                } else if (!in_array($image->getImageMimeType(), $imageConfig->getAllowedFileTypes(), true)) {
                    if (!array_key_exists($imageName . '.' . $idx, $errors)) {
                        $errors[$imageName . '.' . $idx] = [];
                    }
                    $errors[$imageName . '.' . $idx][] = sprintf(
                        RecordValueHelpers::getErrorMessage($localizations, $column::IMAGE_TYPE_IS_NOT_ALLOWED),
                        $image->getImageMimeType(),
                        $imageName,
                        implode(', ', $imageConfig->getAllowedFileTypes())
                    );
                } else if ($file->getSize() / 1024 > $imageConfig->getMaxFileSize()) {
                    if (!array_key_exists($imageName . '.' . $idx, $errors)) {
                        $errors[$imageName . '.' . $idx] = [];
                    }
                    $errors[$imageName . '.' . $idx][] = sprintf(
                        RecordValueHelpers::getErrorMessage($localizations, $column::FILE_SIZE_IS_TOO_LARGE),
                        $imageName,
                        $imageConfig->getMaxFileSize()
                    );
                }
            }
        }
        return $errors;
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
     * @throws \PeskyORM\Exception\RecordNotFoundException
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
        /** @var array $newFiles */
        $updates = $valueContainer->pullDataForSavingExtender();
        if (empty($updates)) {
            return;
        }

        /** @var ImagesColumn $column */
        $column = $valueContainer->getColumn();
        $record = $valueContainer->getRecord();
        $deletedFiles = (array)array_get($updates, 'delete', []);
        foreach ($deletedFiles as $imageName => $files) {
            $imageConfig = $column->getImageConfiguration($imageName);
            foreach ($files as $fileInfo) {
                $existingFileInfo = FileInfo::fromArray($fileInfo, $imageConfig, $record);
                \File::delete($existingFileInfo->getAbsoluteFilePath());
                \File::cleanDirectory($existingFileInfo->getAbsolutePathToModifiedImagesFolder());
            }
        }

        $newFiles = (array)array_get($updates, 'new', []);
        if (!empty($newFiles)) {
            $newValue = json_decode($valueContainer->getValue(), true) ?: [];
            /** @var array $fileUploads */
            foreach ($newFiles as $imageName => $fileUploads) {
                if (empty($newValue[$imageName])) {
                    $newValue[$imageName] = [];
                }
                $newValue[$imageName] = static::storeUploadedFiles(
                    $valueContainer->getRecord(),
                    $column->getImageConfiguration($imageName),
                    $fileUploads,
                    $newValue[$imageName]
                );
                if (empty($newValue[$imageName])) {
                    unset($newValue[$imageName]);
                } else {
                    $newValue[$imageName] = static::reorderGroupOfFiles($newValue[$imageName]);
                }
            }
            $valueContainer->getRecord()
                ->unsetValue($valueContainer->getColumn()) //< to avoid merging
                ->begin()
                ->updateValue($valueContainer->getColumn(), $newValue, false)
                ->commit();
        }
    }

    /**
     * @param RecordInterface $record
     * @param ImageConfig $imageConfig
     * @param array $fileUploads
     * @return array
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \UnexpectedValueException
     */
    static protected function storeUploadedFiles(
        RecordInterface $record,
        ImageConfig $imageConfig,
        array $fileUploads,
        array $existingFiles
    ) {
        $baseSuffix = time();
        $dir = $imageConfig->getAbsolutePathToFileFolder($record);
        if ($imageConfig->getMaxFilesCount() === 1) {
            \File::cleanDirectory($dir);
        }
        $filesSaved = 0;
        foreach ($fileUploads as $uploadInfo) {
            $file = array_get($uploadInfo, 'file', false);
            if (!empty($file)) {
                $fileInfo = FileInfo::fromSplFileInfo($file, $imageConfig, $record, (string)$baseSuffix . (string)$filesSaved);
                $fileInfo->setPosition(array_get($uploadInfo, 'position', time() + $filesSaved));
                $filesSaved++;
                // save not modified file to $dir
                if ($file instanceof UploadedFile) {
                    $file->move($dir, $fileInfo->getFileNameWithExtension());
                } else {
                    /** @var \SplFileInfo $file */
                    \File::copy($file->getRealPath(), $dir . $fileInfo->getFileNameWithExtension());
                }
                // modify image size if needed
                $filePath = $fileInfo->getAbsoluteFilePath();
                $imagick = new \Imagick($filePath);
                if (
                    $imagick->getImageWidth() > $imageConfig->getMaxWidth()
                    && $imagick->resizeImage($imageConfig->getMaxWidth(), 0, $imagick::FILTER_LANCZOS, 1)
                ) {
                    $imagick->writeImage();
                }
                // update value
                $fileInfo->setCustomInfo(array_get($uploadInfo, 'info', []));
                $existingFiles[] = $fileInfo->collectImageInfoForDb();
            }
        }
        if (empty($existingFiles)) {
            \File::cleanDirectory($dir);
        }
        return $existingFiles;
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
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function valueDeleteExtender(RecordValue $valueContainer, $deleteFiles) {
        if ($deleteFiles) {
            /** @var ImagesColumn $column */
            $column = $valueContainer->getColumn();
            $pkValue = $valueContainer->getRecord()->getPrimaryKeyValue();
            foreach ($column as $imageName => $imageConfig) {
                \File::cleanDirectory($imageConfig->getAbsolutePathToFileFolder($valueContainer->getRecord()));
                if ($pkValue) {
                    \File::cleanDirectory($imageConfig->getAbsolutePathToFileFolder($valueContainer->getRecord()));
                }
            }
        }
    }

    /**
     * Formats value according to required $format
     * @param RecordValue $valueContainer
     * @param string $format
     * @return mixed
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    static public function valueFormatter(RecordValue $valueContainer, $format) {
        /** @var ImagesColumn $column */
        $column = $valueContainer->getColumn();
        if ($column->hasFileConfiguration($format)) {
            return $valueContainer->getCustomInfo(
                'file_info:' . $format,
                function () use ($valueContainer, $format, $column) {
                    // return FileInfo object or array of FileInfo objects by image config name provided via $format
                    $record = $valueContainer->getRecord();
                    $value = $record->getValue($column->getName(), 'array');
                    $imageConfig = $column->getImageConfiguration($format);
                    $ret = [];
                    if (!empty($value[$format]) && is_array($value[$format])) {
                        foreach ($value[$format] as $imageInfoArray) {
                            if (static::isFileInfoArray($imageInfoArray)) {
                                $imageInfo = FileInfo::fromArray($imageInfoArray, $imageConfig, $record);
                                if ($imageInfo->exists()) {
                                    $ret[] = $imageInfo;
                                }
                            }
                        }
                        /*for ($i = count($ret); $i < $imageConfig->getMaxFilesCount(); $i++) {
                            $ret[] = NoFileInfo::create();
                        }*/
                    }
                    return $ret;
                },
                true
            );
        } else if ($format === 'file_info_arrays') {
            return $valueContainer->getCustomInfo(
                'file_info:all',
                function () use ($valueContainer, $column) {
                    $ret = [];
                    foreach ($column as $imageConfig) {
                        /** @noinspection AmbiguousMethodsCallsInArrayMappingInspection */
                        $ret[$imageConfig->getName()] = static::valueFormatter($valueContainer, $imageConfig->getName());
                    }
                    return $ret;
                },
                true
            );
        } else if (in_array($format, ['urls', 'urls_with_timestamp', 'paths'], true)) {
            return $valueContainer->getCustomInfo(
                'format:' . $format,
                function () use ($valueContainer, $format, $column) {
                    $value = parent::valueFormatter($valueContainer, 'array');
                    $ret = [];
                    foreach ($value as $imageName => $imageInfo) {
                        if (is_array($imageInfo)) {
                            $imageConfig = $column->getImageConfiguration($imageName);
                            $ret[$imageName] = [];
                            foreach ($imageInfo as $realImageInfo) {
                                if (static::isFileInfoArray($realImageInfo)) {
                                    $fileInfo = FileInfo::fromArray($realImageInfo, $imageConfig, $valueContainer->getRecord());
                                    if (!$fileInfo->exists()) {
                                        continue;
                                    }
                                    if ($format === 'paths') {
                                        $ret[$imageName][] = $fileInfo->getAbsoluteFilePath();
                                    } else {
                                        $url = $fileInfo->getAbsoluteUrl();
                                        if ($format === 'urls_with_timestamp') {
                                            $url .= '?_' . time();
                                        }
                                        $ret[$imageName][] = $url;
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