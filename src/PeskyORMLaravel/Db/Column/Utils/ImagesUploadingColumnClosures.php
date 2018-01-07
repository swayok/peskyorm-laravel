<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
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
     * @throws \PDOException
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
        if (count($normaizledValue)) {
            $newFiles = [];
            $deleteFiles = [];
            $uuidsOfFilesToDelete = [];
            $knownUuids = [];
            foreach ($normaizledValue as $imageName => $images) {
                foreach ($images as $imageInfo) {
                    if (array_key_exists('file', $imageInfo)) {
                        $imageInfo['delete'] = 1;
                        $newFiles[$imageName][] = $imageInfo;
                    }
                    if (!empty($imageInfo['uuid'])) {
                        $knownUuids[$imageName][] = $imageInfo['uuid'];
                        if (array_get($imageInfo, 'delete', false)) {
                            $uuidsOfFilesToDelete[$imageName][] = $imageInfo['uuid'];
                        }
                    }
                }
            }

            $oldValue = $valueContainer->getValue();
            if (!is_array($oldValue)) {
                $oldValue = json_decode($oldValue, true);
                if (is_array($oldValue) && !empty($oldValue)) {
                    $oldValue = static::valueNormalizer($oldValue, false, $column);
                } else {
                    $oldValue = [];
                }
            }

            $valueContainer->setIsFromDb(false);
            if (!empty($oldValue)) {
                foreach ($oldValue as $imageName => $images) {
                    foreach ($images as $index => $image) {
                        if (!empty($image['uuid'])) {
                            $uuid = $image['uuid'];
                        } else {
                            $imageConfig = $column->getImageConfiguration($imageName);
                            $uuid = FileInfo::fromArray(
                                    $image,
                                    $imageConfig,
                                    $valueContainer->getRecord()
                                )->getUuid();
                        }
                        if (in_array($uuid, $uuidsOfFilesToDelete, true)) {
                            $deleteFiles[$imageName] = $image;
                            unset($oldValue[$imageName][$index]);
                        }
                    }
                }
            }
            $json = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
            $valueContainer
                ->setRawValue($oldValue, $json, false)
                ->setValidValue($json, $oldValue);
            if (!empty($newFiles) || !empty($deleteFiles)) {
                $valueContainer->setDataForSavingExtender(['new' => $newFiles, 'delete' => $deleteFiles]);
            }
        } else {
            if ($valueContainer->hasValue()) {
                $valueContainer->setDataForSavingExtender(['new' => [], 'delete' => $valueContainer->getValue()]);
            }
            $valueContainer->setRawValue('{}', '{}', false)->setValidValue('{}', '{}');
        }
        return $valueContainer;
    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column|ImagesColumn $column
     * @return array
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
            if ($value[$imageName] instanceof \SplFileInfo) {
                $value[$imageName] = [['file' => $value[$imageName]]];
            }
            if (!is_array($value[$imageName])) {
                unset($value[$imageName]);
            }
            if (static::isFileInfoArray($value[$imageName])) {
                // not an upload but file info
                $value[$imageName] = [$value[$imageName]];
                continue;
            } else {
                if (array_has($value[$imageName], 'file') || array_has($value[$imageName], 'deleted')) {
                    // normalize uploaded file info to be indexed array with file uploads inside
                    $value[$imageName] = [$value[$imageName]];
                }
                $normailzedData = [];
                foreach ($value[$imageName] as $idx => $fileUploadInfo) {
                    if (static::isFileInfoArray($fileUploadInfo)) {
                        unset($fileUploadInfo['deleted']);
                    } else if (
                        !is_int($idx)
                        || (
                            empty($fileUploadInfo['file'])
                            && !(bool)array_get($fileUploadInfo, 'deleted', false)
                        )
                    ) {
                        if (!empty($fileUploadInfo['file_data'])) {
                            $base64FileInfo = json_decode($fileUploadInfo['file_data'], true);
                            if (is_array($base64FileInfo) && array_has($base64FileInfo, ['data', 'name', 'extension'])) {
                                $fileUploadInfo = [
                                    'file' => new Base64UploadedFile(
                                        $base64FileInfo['data'],
                                        rtrim($base64FileInfo['name'] . '.' . $base64FileInfo['extension'], '.')
                                    )
                                ];
                            } else {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } else {
                        $fileUploadInfo['deleted'] = (bool)array_get($fileUploadInfo, 'deleted', false);
                    }
                    $normailzedData[] = $fileUploadInfo;
                }
                $value[$imageName] = $normailzedData;
            }
        }
        return array_intersect_key($value, array_flip($imagesNames));
    }


    /**
     * Validates value. Uses valueValidatorExtender
     * @param RecordValue|mixed $value
     * @param bool $isFromDb
     * @param Column|ImagesColumn $column
     * @return array
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
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
        $baseSuffix = time();
        if (!empty($newFiles)) {
            $newValue = json_decode($valueContainer->getValue(), true) ?: [];
            /** @var array $fileUploads */
            foreach ($newFiles as $imageName => $fileUploads) {
                $imageConfig = $column->getImageConfiguration($imageName);
                $dir = $imageConfig->getAbsolutePathToFileFolder($valueContainer->getRecord());
                if ($imageConfig->getMaxFilesCount() === 1) {
                    \File::cleanDirectory($dir);
                }
                $filesSaved = 0;
                foreach ($fileUploads as $uploadInfo) {
                    $file = array_get($uploadInfo, 'file', false);
                    if (!empty($file)) {
                        $fileInfo = FileInfo::fromSplFileInfo($file, $imageConfig, $valueContainer->getRecord(), $baseSuffix + $filesSaved);
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
                        $newValue[$imageName][] = $fileInfo->collectImageInfoForDb();
                    }
                }
                if (empty($newValue[$imageName])) {
                    \File::cleanDirectory($dir);
                    unset($newValue[$imageName]);
                } else {
                    // todo reorder here or not?
                }
                $valueContainer->getRecord()
                    ->unsetValue($valueContainer->getColumn()) //< to avoid merging
                    ->begin()
                    ->updateValue($valueContainer->getColumn(), $newValue, false)
                    ->commit();
            }
        }
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