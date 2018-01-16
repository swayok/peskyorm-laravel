<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORMLaravel\Db\Column\Utils\ImagesGroupConfig;
use PeskyORMLaravel\Db\Column\Utils\ImagesUploadingColumnClosures;

class ImagesColumn extends FilesColumn {

    protected $defaultClosuresClass = ImagesUploadingColumnClosures::class;
    protected $fileConfigClass = ImagesGroupConfig::class;

    public function isItAnImage() {
        return true;
    }

    /**
     * @param string $name - image field name
     * @param \Closure $configurator = function (ImagesGroupConfig $imageConfig) { //modify $imageConfig }
     * @return $this
     */
    public function addImagesGroupConfiguration($name, \Closure $configurator = null) {
        return $this->addFilesGroupConfiguration($name, $configurator);
    }

    /**
     * @return ImagesGroupConfig[]
     * @throws \UnexpectedValueException
     */
    public function getImagesGroupsConfigurations() {
        return $this->getFilesGroupsConfigurations();
    }

    /**
     * @param string $name
     * @return ImagesGroupConfig
     * @throws \UnexpectedValueException
     */
    public function getImagesGroupConfiguration($name) {
        return $this->getFilesGroupConfiguration($name);
    }

    /**
     * @return bool
     */
    public function hasImagesGroupsConfigurations() {
        return $this->hasFilesGroupsConfigurations();
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasImagesGroupConfiguration($name) {
        return $this->hasFilesGroupConfiguration($name);
    }

}