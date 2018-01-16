<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\RecordValue;
use PeskyORM\ORM\RecordValueHelpers;
use PeskyORMLaravel\Db\Column\ImagesColumn;
use Swayok\Utils\ValidateValue;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class ImagesUploadingColumnClosures extends FilesUploadingColumnClosures {

    /**
     * Validate uploaded file contents (mime type, size, etc.)
     * @param Column|ImagesColumn $column
     * @param FileConfig $fileConfig
     * @param SymfonyUploadedFile $file
     * @param int $fileIndex
     * @param array $errors
     * @return bool
     */
    static protected function validateUploadedFileContents(
        Column $column,
        FileConfig $fileConfig,
        SymfonyUploadedFile $file,
        $fileIndex,
        array &$errors
    ) {
        if (!parent::validateUploadedFileContents($column, $fileConfig, $file, $fileIndex, $errors)) {
            return false;
        }

        $filesGroupName = $fileConfig->getName();
        $localizations = $column::getValidationErrorsMessages();
        $errorsKey = $filesGroupName . '.' . $fileIndex;

        if (!ValidateValue::isUploadedImage($file, true)) {
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::VALUE_MUST_BE_IMAGE),
                $filesGroupName
            );
        }
        $imagick = new \Imagick($file->getRealPath());
        if (!$imagick->valid() || ($imagick->getImageMimeType() === 'image/jpeg' && ValidateValue::isCorruptedJpeg($file->getRealPath()))) {
            if (!array_key_exists($errorsKey, $errors)) {
                $errors[$errorsKey] = [];
            }
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::FILE_IS_NOT_A_VALID_IMAGE),
                $filesGroupName
            );
        } else if (!in_array($imagick->getImageMimeType(), $fileConfig->getAllowedFileTypes(), true)) {
            if (!array_key_exists($errorsKey, $errors)) {
                $errors[$errorsKey] = [];
            }
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::IMAGE_TYPE_IS_NOT_ALLOWED),
                $imagick->getImageMimeType(),
                $filesGroupName,
                implode(', ', $fileConfig->getAllowedFileTypes())
            );
        }
        $imagick->destroy();
        return empty($errors[$errorsKey]);
    }

    /**
     * @param FileInfo $fileInfo
     * @throws \UnexpectedValueException
     */
    static protected function deleteExistingFiles(FileInfo $fileInfo) {
        parent::deleteExistingFiles($fileInfo);
        \File::cleanDirectory($fileInfo->getAbsolutePathToModifiedImagesFolder());
    }

    /**
     * @param FileInfo $fileInfo
     * @param FileConfig|ImageConfig $fileConfig
     */
    static protected function modifyUploadedFileAfterSaveToFs(FileInfo $fileInfo, FileConfig $fileConfig) {
        // modify image size if needed
        $filePath = $fileInfo->getAbsoluteFilePath();
        $imagick = new \Imagick($filePath);
        if (
            $imagick->getImageWidth() > $fileConfig->getMaxWidth()
            && $imagick->resizeImage($fileConfig->getMaxWidth(), 0, $imagick::FILTER_LANCZOS, 1)
        ) {
            $imagick->writeImage();
        }
        $imagick->destroy();
    }

}