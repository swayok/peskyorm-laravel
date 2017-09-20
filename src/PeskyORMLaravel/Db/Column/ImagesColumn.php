<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORMLaravel\Db\Column\Utils\ImageConfig;
use PeskyORMLaravel\Db\Column\Utils\ImagesUploadingColumnClosures;

class ImagesColumn extends FilesColumn {

    protected $defaultClosuresClass = ImagesUploadingColumnClosures::class;
    protected $fileConfigClass = ImageConfig::class;

    public function isItAnImage() {
        return true;
    }

    /**
     * @param string $name - image field name
     * @param \Closure $configurator = function (ImageConfig $imageConfig) { //modify $imageConfig }
     * @return $this
     */
    public function addImageConfiguration($name, \Closure $configurator = null) {
        return $this->addFileConfiguration($name, $configurator);
    }

    /**
     * @return ImageConfig[]
     * @throws \UnexpectedValueException
     */
    public function getImagesConfigurations() {
        return $this->getFilesConfigurations();
    }

    /**
     * @param string $name
     * @return ImageConfig
     * @throws \UnexpectedValueException
     */
    public function getImageConfiguration($name) {
        return $this->getFileConfiguration($name);
    }

    /**
     * @return bool
     */
    public function hasImagesConfigurations() {
        return $this->hasFilesConfigurations();
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasImageConfiguration($name) {
        return $this->hasFileConfiguration($name);
    }

}