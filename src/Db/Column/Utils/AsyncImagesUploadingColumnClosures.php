<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyCMF\Scaffold\Form\UploadedTempFileInfo;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordValueHelpers;
use PeskyORMLaravel\Db\Column\ImagesColumn;
use Swayok\Utils\ValidateValue;

class AsyncImagesUploadingColumnClosures extends AsyncFilesUploadingColumnClosures {

    /**
     * Validate uploaded file contents (mime type, size, etc.)
     * @param Column|ImagesColumn $column
     * @param FileConfigInterface $fileConfig
     * @param UploadedTempFileInfo $fileInfo
     * @param int $fileIndex
     * @param array $errors
     * @return bool
     * @throws \ImagickException
     */
    protected static function validateUploadedFileContents(
        Column $column,
        FileConfigInterface $fileConfig,
        UploadedTempFileInfo $fileInfo,
        $fileIndex,
        array &$errors
    ) {
        if (!parent::validateUploadedFileContents($column, $fileConfig, $fileInfo, $fileIndex, $errors)) {
            return false;
        }

        $filesGroupName = $fileConfig->getName();
        $localizations = $column::getValidationErrorsMessages();
        $errorsKey = $filesGroupName . '.' . $fileIndex;

        $imagick = new \Imagick($fileInfo->getRealPath());
        if (!$imagick->valid() || ($imagick->getImageMimeType() === 'image/jpeg' && ValidateValue::isCorruptedJpeg($fileInfo->getRealPath()))) {
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::FILE_IS_NOT_A_VALID_IMAGE),
                $filesGroupName
            );
        } else if (!in_array($imagick->getImageMimeType(), $fileConfig->getAllowedMimeTypes(), true)) {
            $errors[$errorsKey][] = sprintf(
                RecordValueHelpers::getErrorMessage($localizations, $column::IMAGE_TYPE_IS_NOT_ALLOWED),
                $imagick->getImageMimeType(),
                $filesGroupName,
                implode(', ', $fileConfig->getAllowedMimeTypes())
            );
        }
        $imagick->destroy();
        return empty($errors[$errorsKey]);
    }

    /**
     * @param FileInfo $fileInfo
     * @throws \UnexpectedValueException
     */
    protected static function deleteExistingFile(FileInfo $fileInfo) {
        parent::deleteExistingFile($fileInfo);
        \File::cleanDirectory($fileInfo->getAbsolutePathToModifiedImagesFolder());
    }

    /**
     * @param FileInfo $fileInfo
     * @param FileConfigInterface $fileConfig
     * @throws \UnexpectedValueException
     * @throws \ImagickException
     */
    protected static function modifyUploadedFileAfterSaveToFs(FileInfo $fileInfo, FileConfigInterface $fileConfig) {
        // modify image size if needed
        $imagick = new \Imagick($fileInfo->getAbsoluteFilePath());
        // aspect ratio
        $imageChanged = false;
        if (!empty($fileConfig->getAspectRatio())) {
            $imageAspectRatio = $imagick->getImageWidth() / $imagick->getImageHeight();
            if (round($imageAspectRatio, 3) !== round($fileConfig->getAspectRatio(), 3)) {
                if ($fileConfig->getAspectRatio() > $imageAspectRatio) {
                    $newHeight = round($imagick->getImageWidth() / $fileConfig->getAspectRatio());
                    $newWidth = $imagick->getImageWidth();
                } else {
                    $newHeight = $imagick->getImageHeight();
                    $newWidth = round($imagick->getImageHeight() * $fileConfig->getAspectRatio());
                }
                $success = $imagick->cropImage(
                    $newWidth,
                    $newHeight,
                    abs(round(($newWidth - $imagick->getImageWidth()) / 2)),
                    abs(round(($newHeight - $imagick->getImageHeight()) / 2))
                );
                if ($success) {
                    $imageChanged = true;
                }
            }
        }
        // width/height limits
        if (
            $imagick->getImageWidth() > $fileConfig->getMaxWidth()
            && $imagick->resizeImage($fileConfig->getMaxWidth(), 0, $imagick::FILTER_LANCZOS, 1)
        ) {
            $imageChanged = true;
        }
        if ($imageChanged) {
            $imagick->writeImage();
        }
        $imagick->destroy();
    }

}
