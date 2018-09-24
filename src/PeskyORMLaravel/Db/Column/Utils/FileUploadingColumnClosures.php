<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\DefaultColumnClosures;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\RecordValueHelpers;
use PeskyORMLaravel\Db\Column\FileColumn;
use PeskyORMLaravel\Db\Column\ImageColumn;
use Swayok\Utils\ValidateValue;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class FileUploadingColumnClosures extends DefaultColumnClosures {

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
        /** @var FileColumn $column */
        $column = $valueContainer->getColumn();
        $errors = $column->validateValue($newValue, $isFromDb);
        if (count($errors) > 0) {
            return $valueContainer->setValidationErrors($errors);
        }
        /** @var array $newValue */
        $normalizedValue = static::valueNormalizer($newValue, $isFromDb, $column);
        if (count($normalizedValue) > 0) {
            $deleteCurrentFile = false;
            $hasNewFile = isset($normalizedValue['file']);
            $updatedValue = $hasNewFile ? [] : $normalizedValue;
            if ($valueContainer->hasValue()) {
                $currentValue = $valueContainer->getValue();
                if (!empty($currentValue) && (is_array($currentValue) || !preg_match('%^(\{\}|\[\])$%', $currentValue))) {
                    if ($hasNewFile || array_get($normalizedValue, 'deleted', false)) {
                        $deleteCurrentFile = true;
                        $updatedValue = [];
                    } else if ($hasNewFile) {
                        $updatedValue = [];
                    }
                }
            }
            $json = json_encode($updatedValue, JSON_UNESCAPED_UNICODE);
            $valueContainer
                ->setIsFromDb(false)
                ->setRawValue($updatedValue, $json, false)
                ->setValidValue($json, $updatedValue);
            if ($hasNewFile || $deleteCurrentFile) {
                $valueContainer->setDataForSavingExtender([
                    'new' => $hasNewFile ? $normalizedValue : null,
                    'delete' => $deleteCurrentFile
                ]);
            }
        }
        return $valueContainer;
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
            /** @var FileColumn $column */
            $column = $valueContainer->getColumn();
            return FileInfo::fromArray(
                    $fileData,
                    $column->getConfiguration(),
                    $valueContainer->getRecord()
                )
                ->getUuid();
        });
    }

    /**
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column|ImageColumn|FileColumn $column
     * @return array
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    static public function valueNormalizer($value, $isFromDb, Column $column) {
        if ($isFromDb && is_string($value)) {
            $value = json_decode($value, true);
        }
        if (empty($value) || (!is_array($value) && !($value instanceof \SplFileInfo))) {
            return [];
        }
        if ($isFromDb) {
            $value = static::normalizeDbValue($value);
        } else {
            if ($value instanceof \SplFileInfo) {
                $value = $isFromDb ? [] : ['file' => $value];
            }
            if (!is_array($value)) {
                return [];
            }
            $value = static::normalizeUploadedFile($value);
        }

        if (empty($value) || !is_array($value)) {
            return [];
        }
        return $value;
    }

    /**
     * @param array $existingFile
     * @return array
     */
    static protected function normalizeDbValue(array $existingFile) {
        if (static::isFileInfoArray($existingFile)) {
            return $existingFile;
        }
        return [];
    }

    /**
     * @param array $fileUploadInfo
     * @return array
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    static protected function normalizeUploadedFile(array $fileUploadInfo) {
        $normailzedData = [];
        if ($fileUploadInfo instanceof \SplFileInfo) {
            $normailzedData = [
                'file' => $fileUploadInfo
            ];
        } else if (static::isFileInfoArray($fileUploadInfo)) {
            // file info array is being saved to DB via static::valueSavingExtender() or manually
            unset($fileUploadInfo['deleted']);
            $normailzedData = $fileUploadInfo;
        } else {
            $fileUploadInfo['deleted'] = (bool)array_get($fileUploadInfo, 'deleted', false);
            if (!empty($fileUploadInfo['file'])) {
                // new file uploaded
                $normailzedData = $fileUploadInfo;
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
                    $normailzedData = $fileUploadInfo;
                }
            } else if (array_has($fileUploadInfo, 'uuid')) {
                if ((bool)array_get($fileUploadInfo, 'deleted', false)) {
                    // old file deleted while new one is not provided
                    $fileUploadInfo['deleted'] = true;
                    unset($fileUploadInfo['file'], $fileUploadInfo['file_data']);
                    $normailzedData = $fileUploadInfo;
                } else if (array_has($fileUploadInfo, 'position')) {
                    // file already exists but may have changed position
                    $normailzedData = [
                        'uuid' => $fileUploadInfo['uuid'],
                        'position' => $fileUploadInfo['position'],
                        'deleted' => false
                    ];
                }
            }
            // ignore any other case
        }
        return $normailzedData;
    }

    /**
     * Validates value. Uses valueValidatorExtender
     * @param mixed $value
     * @param bool $isFromDb
     * @param Column|ImageColumn|FileColumn $column
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
        if (!is_array($value) && !($value instanceof \SplFileInfo)) {
            return [RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_ARRAY)];
        }
        $value = static::valueNormalizer($value, $isFromDb, $column);
        $errors = [];
        /** @noinspection ForeachSourceInspection */
        if (static::isFileInfoArray($value)) {
            return [];
        }
        /** @var bool|\SplFileInfo|array $file */
        $file = array_get($value, 'file') ?: false;
        $isUploadedFile = $file && ValidateValue::isUploadedFile($file, true);

        if (
            !$isUploadedFile
            && !array_get($value, 'deleted', false)
            && empty($value['uuid'])
        ) {
            $errors[] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_FILE),
                $column->getName()
            );
        }
        if (!$isUploadedFile || !empty($errors)) {
            // old file present or only file deletion requested
            return [];
        }
        if (is_array($file)) {
            $file = static::makeUploadedFileFromArray($file);
        } else if (!($file instanceof SymfonyUploadedFile) && ($file instanceof \SplFileInfo)) {
            $file = static::makeUploadedFileFromSplFileInfo($file);
        }
        static::validateUploadedFileContents($column, $file, $errors);
        return $errors;
    }

    /**
     * Validate uploaded file contents (mime type, size, etc.)
     * @param Column|FileColumn|ImageColumn $column
     * @param SymfonyUploadedFile $file
     * @param array $errors
     * @return bool
     */
    static protected function validateUploadedFileContents(
        Column $column,
        SymfonyUploadedFile $file,
        array &$errors
    ) {
        $localizations = $column::getValidationErrorsMessages();
        $fileConfig = $column->getConfiguration();
        $filesGroupName = $fileConfig->getName();

        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
        if (!in_array($mimeType, $fileConfig->getAllowedMimeTypes(), true)) {
            $errors[] = sprintf(
                RecordValueHelpers::getErrorMessage(
                    $localizations,
                    $column->isItAnImage() ? $column::IMAGE_TYPE_IS_NOT_ALLOWED : $column::FILE_TYPE_IS_NOT_ALLOWED
                ),
                $mimeType,
                $filesGroupName,
                implode(', ', $fileConfig->getAllowedMimeTypes())
            );
        } else if ($file->getSize() / 1024 > $fileConfig->getMaxFileSize()) {
            $errors[] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::FILE_SIZE_IS_TOO_LARGE),
                $filesGroupName,
                $fileConfig->getMaxFileSize()
            );
        }
        return !empty($errors);
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

        /** @var FileColumn $column */
        $column = $valueContainer->getColumn();
        $record = $valueContainer->getRecord();
        $fileConfig = $column->getConfiguration();
        // remove old file and its modifications
        if ($isUpdate) {
            \File::cleanDirectory($fileConfig->getAbsolutePathToFileFolder($record));
        }
        // save new file
        $newFile = (array)array_get($updates, 'new', []);
        if (!empty($newFile)) {
            $currentFileInfo = static::storeUploadedFile($record, $fileConfig, $newFile);
            $record
                ->unsetValue($valueContainer->getColumn()) //< to avoid merging
                ->begin()
                ->updateValue($valueContainer->getColumn(), $currentFileInfo, false)
                ->commit();
        }
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
     * @param FileConfig|ImageConfig $fileConfig
     * @param array $uploadInfo
     * @return array
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \UnexpectedValueException
     */
    static protected function storeUploadedFile(RecordInterface $record, FileConfig $fileConfig, array $uploadInfo) {
        $baseSuffix = time();
        $dir = $fileConfig->getAbsolutePathToFileFolder($record);
        if (!\File::isDirectory($dir)) {
            \File::makeDirectory($dir, 0777, true);
        }
        $file = array_get($uploadInfo, 'file', false);
        if (!empty($file)) {
            $fileInfo = FileInfo::fromSplFileInfo($file, $fileConfig, $record, (string)$baseSuffix);
            $fileInfo->setCustomInfo(array_get($uploadInfo, 'info', []));
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
            return $fileInfo->collectImageInfoForDb();
        }
        return [];
    }

    /**
     * Modify uploaded file after it was stroed to file system but before data was saved to DB.
     * You can store additional info via $fileInfo->setCustomInfo() (you may need to merge with existing info)
     * @param FileInfo $fileInfo
     * @param FileConfig|ImageConfig $fileConfig
     */
    static protected function modifyUploadedFileAfterSaveToFs(FileInfo $fileInfo, FileConfig $fileConfig) {

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
            /** @var FileColumn $column */
            $column = $valueContainer->getColumn();
            $fileConfig = $column->getConfiguration();
            $pkValue = $valueContainer->getRecord()->getPrimaryKeyValue();
            \File::cleanDirectory($fileConfig->getAbsolutePathToFileFolder($valueContainer->getRecord()));
            if ($pkValue) {
                \File::cleanDirectory($fileConfig->getAbsolutePathToFileFolder($valueContainer->getRecord()));
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
        /** @var FileColumn $column */
        $column = $valueContainer->getColumn();
        if ($format === 'file_info') {
            // colname_as_file_info
            // returns FileInfo[] or null
            return $valueContainer->getCustomInfo(
                'file_info',
                function () use ($valueContainer, $format, $column) {
                    // return FileInfo object or array of FileInfo objects by image config name provided via $format
                    $record = $valueContainer->getRecord();
                    $value = $record->getValue($column->getName(), 'array');
                    $fileConfig = $column->getConfiguration();
                    if (!empty($value) && is_array($value) && static::isFileInfoArray($value)) {
                        $imageInfo = FileInfo::fromArray($value, $fileConfig, $record);
                        if ($imageInfo->exists()) {
                            return $imageInfo;
                        }
                    }
                    return null;
                },
                true
            );
        } else if (in_array($format, ['url', 'url_with_timestamp', 'path'], true)) {
            // colname_as_url / colname_as_url_with_timestamp / colname_as_path
            // returns string or null
            return $valueContainer->getCustomInfo(
                'format:' . $format,
                function () use ($valueContainer, $format, $column) {
                    $fileInfo = parent::valueFormatter($valueContainer, 'array');
                    if (is_array($fileInfo) && static::isFileInfoArray($fileInfo)) {
                        $fileConfig = $column->getConfiguration();
                        $fileInfo = FileInfo::fromArray($fileInfo, $fileConfig, $valueContainer->getRecord());
                        if ($fileInfo->exists()) {
                            if ($format === 'path') {
                                return $fileInfo->getAbsoluteFilePath();
                            } else {
                                $url = $fileInfo->getAbsoluteUrl();
                                if ($format === 'url_with_timestamp') {
                                    $url .= '?_' . time();
                                }
                                return $url;
                            }
                        }
                    }
                    return null;
                },
                true
            );
        } else {
            return parent::valueFormatter($valueContainer, $format);
        }
    }

    /**
     * @param FileColumn|Column $column
     * @return array
     */
    public static function getValueFormats(Column $column) {
        $defaultFormats = parent::getValueFormats($column);
        $formats = [];
        if ($column instanceof FileColumn) {
            $formats = [
                'file_info',
                'url',
                'url_with_timestamp',
                'path'
            ];
        }
        return array_unique(array_merge($defaultFormats, $formats));
    }
}