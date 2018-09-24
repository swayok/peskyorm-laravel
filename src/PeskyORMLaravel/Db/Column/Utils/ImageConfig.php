<?php

namespace PeskyORMLaravel\Db\Column\Utils;

class ImageConfig extends FileConfig {

    /** @var int */
    protected $maxWidth = 1920;
    /** @var float */
    protected $aspectRatio;

    /**
     * @var array
     */
    protected $defaultAllowedMimeTypes = [
        self::PNG,
        self::JPEG,
        self::SVG,
        self::GIF,
    ];

    /**
     * @return int
     */
    public function getMaxWidth() {
        return $this->maxWidth;
    }

    /**
     * @param int $maxWidth
     * @return $this
     */
    public function setMaxWidth($maxWidth) {
        $this->maxWidth = (int)$maxWidth;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getAspectRatio() {
        return $this->aspectRatio;
    }

    /**
     * @param int $width - for example: 4, 16
     * @param int $height - for example: 3, 9
     * @return $this
     */
    public function setAspectRatio($width, $height) {
        $this->aspectRatio = (float)$width / (float)$height;
        return $this;
    }

    /**
     * @param array $allowedFileTypes - combination of ImagesGroupConfig::PNG, ImagesGroupConfig::JPEG, ImagesGroupConfig::GIF, ImagesGroupConfig::SVG
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAllowedMimeTypes(...$allowedFileTypes) {
        if (count($allowedFileTypes) === 1 && isset($allowedFileTypes[0]) && is_array($allowedFileTypes[0])) {
            $allowedFileTypes = $allowedFileTypes[0];
        }
        parent::setAllowedMimeTypes($allowedFileTypes);
        $unknownTypes = array_diff(
            $this->allowedMimeTypes,
            [static::PNG, static::JPEG, static::GIF, static::SVG],
            $this->allowedMimeTypesAliases
        );
        if (count($unknownTypes) > 0) {
            throw new \InvalidArgumentException(
                '$allowedFileTypes argument contains not supported image types: ' . implode(', ', $unknownTypes)
            );
        }
        return $this;
    }

    public function getConfigsArrayForJs() {
        return array_merge(
            parent::getConfigsArrayForJs(),
            [
                'aspect_ratio' => $this->getAspectRatio(),
                'max_width' => $this->getMaxWidth(),
            ]
        );
    }
}