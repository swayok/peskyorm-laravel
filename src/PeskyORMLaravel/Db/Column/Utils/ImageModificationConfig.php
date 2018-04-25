<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use Swayok\Utils\File;
use Swayok\Utils\Folder;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

class ImageModificationConfig {

    // Note: all fit modes preserve aspect ratio of the original image
    /**
     * Crop image to fit both dimensions with 100% fill + enlarge it if needed (same as css background-size: cover)
     */
    const COVER = 1;
    /**
     * Resize image to fit both dimensions + enlarge it if needed (same as css background-size: contain)
     * Empty space will be filled with specified background color.
     * Note: if target width or height is 0 - just resizes image without any background and empty space
     */
    const CONTAIN = 2;
    /**
     * Downsize image to fit both dimensions; do nothing for images that already fit both dimensions;
     * Resulting dimensions will be within required dimensions. No enlarge, no background fill.
     */
    const RESIZE_LARGER = 3;

    const TOP = 1;
    const CENTER = 2;
    const BOTTOM = 3;
    const LEFT = 4;
    const RIGHT = 5;

    const PNG = MimeTypesHelper::PNG;
    const JPEG = MimeTypesHelper::JPEG;
    const GIF = MimeTypesHelper::GIF;
    const SVG = MimeTypesHelper::SVG;
    const SAME_AS_ORIGINAL = null;

    /** @var string */
    protected $name;
    /** @var int */
    protected $width = 1920;
    /** @var int */
    protected $height = 0;
    /** @var int */
    protected $fitMode = self::RESIZE_LARGER;
    /** @var string|null */
    protected $backgroundColor;
    /** @var int */
    protected $verticalAlign = self::CENTER;
    /** @var int */
    protected $horizontalAlign = self::CENTER;
    /**
     * One of ImageModificationConfig::SAME_AS_ORIGINAL, ImageModificationConfig::PNG,
     * ImageModificationConfig::JPEG, ImageModificationConfig::GIF, ImageModificationConfig::SVG
     * @var
     */
    protected $alterImageType = self::SAME_AS_ORIGINAL;
    /**
     * Image compression quelity in percents
     * @var int
     */
    protected $compressionQuality = 95;

    /**
     * @param string $modificationName - modification name
     * @return static
     * @throws \InvalidArgumentException
     */
    static public function create($modificationName) {
        return new static($modificationName);
    }

    /**
     * @param string $modificationName - modification name
     * @throws \InvalidArgumentException
     */
    public function __construct($modificationName) {
        $this->setModificationName($modificationName);
    }

    /**
     * @return string
     */
    protected function getModificationName() {
        return $this->name;
    }

    /**
     * @param string $modificationName
     * @return $this
     */
    protected function setModificationName($modificationName) {
        if (empty($modificationName) || !is_string($modificationName)) {
            throw new \InvalidArgumentException('$modificationName argument must be a not empty string');
        }
        $this->name = $modificationName;
        return $this;
    }

    /**
     * @return int
     */
    protected function getWidth() {
        return $this->width;
    }

    /**
     * @param int $width
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setWidth($width) {
        if ((int)$width < 0) {
            throw new \InvalidArgumentException('Width must be a positive integer number');
        }
        $this->width = (int)$width;
        return $this;
    }

    /**
     * @return int
     */
    protected function getHeight() {
        return $this->height;
    }

    /**
     * @param int $height
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setHeight($height) {
        if ((int)$height < 0) {
            throw new \InvalidArgumentException('Height must be a positive integer number');
        }
        $this->height = (int)$height;
        return $this;
    }

    /**
     * @return int
     */
    protected function getFitMode() {
        return $this->fitMode;
    }

    /**
     * @param int $fitMode - one of self::COVER, self::CONTAIN, self::RESIZE_LARGER
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setFitMode($fitMode) {
        if (!in_array($fitMode, [static::CONTAIN, static::COVER, static::RESIZE_LARGER], true)) {
            throw new \InvalidArgumentException(
                '$fitMode argument must be one of: ImagesGroupConfig::COVER, ImagesGroupConfig::CONTAIN, ImagesGroupConfig::RESIZE_LARGER'
            );
        }
        $this->fitMode = $fitMode;
        return $this;
    }

    /**
     * @return int
     */
    protected function getVerticalAlign() {
        return $this->verticalAlign;
    }

    /**
     * Set vertical align for images with fit mode CONTAIN and COVER
     * @param int $verticalAlign
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setVerticalAlign($verticalAlign) {
        if (!in_array($verticalAlign, [static::TOP, static::CENTER, static::BOTTOM], true)) {
            throw new \InvalidArgumentException(
                '$verticalAlign argument must be one of: ImagesGroupConfig::TOP, ImagesGroupConfig::CENTER, ImagesGroupConfig::BOTTOM'
            );
        }
        $this->verticalAlign = $verticalAlign;
        return $this;
    }

    /**
     * @return int
     */
    protected function getHorizontalAlign() {
        return $this->horizontalAlign;
    }

    /**
     * @param int $horizontalAlign
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setHorizontalAlign($horizontalAlign) {
        if (!in_array($horizontalAlign, [static::LEFT, static::CENTER, static::RIGHT], true)) {
            throw new \InvalidArgumentException(
                '$horizontalAlign argument must be one of: ImagesGroupConfig::LEFT, ImagesGroupConfig::CENTER, ImagesGroupConfig::RIGHT'
            );
        }
        $this->horizontalAlign = $horizontalAlign;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getBackgroundColor() {
        return $this->backgroundColor;
    }

    /**
     * @param string $default
     * @return \ImagickPixel
     */
    protected function getBackgroundColorForImagick($default = '#FFFFFF') {
        $bgColor = $this->getBackgroundColor() ?: $default;
        return new \ImagickPixel($bgColor);
    }

    /**
     * @param string $backgroundColor
     *      - hex: color with leading #: #FFFFFF or #FFF for white bg;
     *      - hexa: color with leading # and alpha channel: #FFFA, #FFFFFFAA for white bg with a bit of transparency;
     *      - rgb: rgb(100%, 0%, 0%) or rgb(255, 0, 0);
     *      - rgba: rgba(100%, 0%, 0%, 1.0) or rgba(255, 0, 0, 1.0);
     *      - hsb: hsb(33.3333%, 100%, 75%);
     *      - hsl: hsl(120, 255, 191.25);
     *      - cmyk: cmyk(0.9, 0.48, 0.83, 0.50);
     *      - name: only 'transparent', 'white', 'black' allowed
     *      - null: transparency (png) or white bg (not png)
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setBackgroundColor($backgroundColor) {
        if (empty($backgroundColor)) {
            $this->backgroundColor = null;
        } else {
            $patterns = [
                '#[a-f0-9]{3,4}', //< #FFF / #FFFA
                '#[a-f0-9]{6}([a-f0-9]{2})?', //< #FFFFFF / #FFFFFFAA
                '(^rgb|hsb|hsl)\s*\((\s*\d+(\.\d*)?\s*\%?\s*,){2}(\s*\d+(\.\d*)?\s*\%?\s*)\)',
                '(^rgba|cmyk)\s*\((\s*\d+(\.\d*)?\s*\%?\s*,){3}(\s*\d+(\.\d*)?\s*\%?\s*)\)',
                '(transparent|white|black)'
            ];
            if (!preg_match('%(' . implode('|', $patterns) . ')%iu', $backgroundColor)) {
                throw new \InvalidArgumentException('$backgroundColor is invalid or not supported');
            }
            $this->backgroundColor = $backgroundColor;
        }
        return $this;
    }

    /**
     * Change image type
     * @param $mimeType - ImageModificationConfig::SAME_AS_ORIGINAL, ImageModificationConfig::PNG,
     * ImageModificationConfig::JPEG, ImageModificationConfig::GIF, ImageModificationConfig::SVG
     * @throws \InvalidArgumentException
     */
    public function setImageType($mimeType) {
        if (!in_array($mimeType, [self::SAME_AS_ORIGINAL, self::GIF, self::PNG, self::JPEG, self::SVG], true)) {
            throw new \InvalidArgumentException("\$mimeType '{$mimeType}' is not supported");
        }
        $this->alterImageType = $mimeType;
    }

    /**
     * @return null|string
     */
    protected function getImageType() {
        return $this->alterImageType;
    }

    /**
     * @return int
     */
    public function getCompressionQuality(): int {
        return $this->compressionQuality;
    }

    /**
     * @param int $compressionQuality
     * @return $this
     */
    public function setCompressionQuality($compressionQuality) {
        if ((int)$compressionQuality <= 1 || (int)$compressionQuality > 100) {
            throw new \InvalidArgumentException('Compression quality must be between 1 and 100');
        }
        $this->compressionQuality = (int)$compressionQuality;
        return $this;
    }

    /**
     * @param string $absoluteFilePath
     * @param string $modifiedImagesFolder
     * @return \Symfony\Component\HttpFoundation\File\File
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
     * @throws \ImagickException
     */
    public function applyModificationTo($absoluteFilePath, $modifiedImagesFolder) {
        $originalFile = new SymfonyFile($absoluteFilePath, true);
        $newFileMimeType = $this->getImageType() ?: $originalFile->getMimeType();
        $newFileExt = MimeTypesHelper::getExtensionForMimeType($newFileMimeType);
        $baseFileName = preg_replace('%\.' . $originalFile->getExtension() . '$%i', '', $originalFile->getFilename());
        $newFileName = $baseFileName . '-' . $this->getModificationName();
        $newFilePath = rtrim($modifiedImagesFolder, '/\\ ') . DIRECTORY_SEPARATOR . $newFileName . '.' . $newFileExt;
        Folder::load($modifiedImagesFolder, true, 0777);
        $expectedWidth = $this->getWidth();
        $expectedHeight = $this->getHeight();
        if ($expectedWidth === 0 && $expectedHeight === 0) {
            throw new \UnexpectedValueException('Image modification has no valid width and height');
        }
        if (File::exist($newFilePath)) {
            list($w, $h) = getimagesize($newFilePath);
            if (!$expectedWidth) {
                $expectedWidth = $w;
            } else if (!$expectedHeight) {
                $expectedHeight = $h;
            }
            if ($w === $expectedWidth && $h === $expectedHeight) {
                return new SymfonyFile($newFilePath, false);
            } else {
                File::remove($newFilePath);
            }
        }
        return $this->modifyFile($originalFile, $newFilePath, $newFileMimeType);
    }

    /**
     * @param SymfonyFile $originalFile
     * @param string $newFilePath
     * @param string $newFileMimeType
     * @return \Symfony\Component\HttpFoundation\File\File
     * @throws \ImagickException
     */
    protected function modifyFile(SymfonyFile $originalFile, $newFilePath, $newFileMimeType) {
        $targetWidth = $this->getWidth();
        $targetHeight = $this->getHeight();
        $fit = $this->getFitMode();
        $imagick = new \Imagick($originalFile->getRealPath());
        $imagick->setImageFormat(str_replace('image/', '' ,$newFileMimeType));
        $imagick->setCompressionQuality($this->getCompressionQuality());
        switch ($fit) {
            case static::RESIZE_LARGER:
                // Downsize image to fit both dimensions;
                // Do nothing for images that already fit both dimensions (no enlarge);
                if ($imagick->getImageWidth() > $targetWidth || $imagick->getImageHeight() > $targetHeight) {
                    $bestfit = !($targetWidth === 0 || $targetHeight === 0);
                    $imagick->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 0.9, $bestfit);
                }
                break;
            case static::COVER:
                // Crop image to fit both dimensions with 100% fill + enlarge it if needed (same as css background-size: cover)
                if ($targetWidth === 0 || $targetHeight === 0) {
                    throw new \UnexpectedValueException('Width and height must be specified for \'cover\' fitting');
                }
                if ($imagick->getImageWidth() > $imagick->getImageHeight()) {
                    $resizeWidth = $imagick->getImageWidth() * $targetHeight / $imagick->getImageHeight();
                    $resizeHeight = $targetHeight;
                } else {
                    $resizeWidth = $targetWidth;
                    $resizeHeight = $imagick->getImageHeight() * $targetWidth / $imagick->getImageWidth();
                }
                $imagick->resizeImage($resizeWidth, $resizeHeight, \Imagick::FILTER_LANCZOS, 0.9);
                list($offsetX, $offsetY) = $this->calculateOffsets($imagick, $targetWidth, $targetHeight);
                $imagick->cropImage($targetWidth, $targetHeight, abs($offsetX), abs($offsetY));
                break;
            case static::CONTAIN:
                // Resize image to fit both dimensions + enlarge it if needed (same as css background-size: contain)
                // Empty space will be filled with specified background color
                $resizeWidth = $targetWidth;
                $resizeHeight = $targetHeight;
                if ($targetHeight !== 0 && $targetWidth !== 0) {
                    $resized = $imagick;
                    $resized->resizeImage($resizeWidth, $resizeHeight, \Imagick::FILTER_LANCZOS, 0.9, true);
                    $imagick = new \Imagick();
                    $imagick->newImage(
                        $targetWidth,
                        $targetHeight,
                        $this->getBackgroundColorForImagick($newFileMimeType === static::PNG ? 'transparent' : 'FFFFFF')
                    );
                    list($offsetX, $offsetY) = $this->calculateOffsets($resized, $targetWidth, $targetHeight);
                    $imagick->compositeImage($resized, \Imagick::COMPOSITE_OVER, $offsetX, $offsetY);
                    $resized->destroy();
                } else {
                    // target width or height is 0 - just resize image with best fit (downsize or enlarge)
                    $imagick->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 0.9, false);
                }
                break;
        }
        $imagick->writeImage($newFilePath);
        $imagick->destroy();
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new SymfonyFile($newFilePath, false);
    }

    /**
     * @param \Imagick $image
     * @param int $targetWidth
     * @param int $targetHeight
     * @return array
     */
    protected function calculateOffsets(\Imagick $image, $targetWidth, $targetHeight) {
        $offsetY = $offsetX = 0;
        if ($image->getImageWidth() !== $targetWidth) {
            $diff = $targetWidth - $image->getImageWidth();
            $hAlign = $this->getHorizontalAlign();
            switch ($hAlign) {
                case static::CENTER:
                    $offsetX = (int)round($diff / 2);
                    break;
                case static::LEFT:
                    break;
                case static::RIGHT:
                    $offsetX = $diff;
                    break;
            }
        } else if ($image->getImageHeight() !== $targetHeight) {
            $diff = $targetHeight - $image->getImageHeight();
            $vAlign = $this->getVerticalAlign();
            switch ($vAlign) {
                case static::CENTER:
                    $offsetY = (int)round($diff / 2);
                    break;
                case static::TOP:
                    break;
                case static::BOTTOM:
                    $offsetY = $diff;
                    break;
            }
        }
        return [$offsetX, $offsetY];
    }

}