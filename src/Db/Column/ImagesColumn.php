<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Column;

use PeskyORMLaravel\Db\Column\Utils\ImagesGroupConfig;
use PeskyORMLaravel\Db\Column\Utils\ImagesUploadingColumnClosures;

class ImagesColumn extends FilesColumn
{
    
    protected $defaultClosuresClass = ImagesUploadingColumnClosures::class;
    protected $fileConfigClass = ImagesGroupConfig::class;
    
    public function isItAnImage(): bool
    {
        return true;
    }
    
    /**
     * @param string $name - image field name
     * @param \Closure|null $configurator = function (ImagesGroupConfig $imageConfig) { //modify $imageConfig }
     * @return static
     */
    public function addImagesGroupConfiguration(string $name, ?\Closure $configurator = null)
    {
        return $this->addFilesGroupConfiguration($name, $configurator);
    }
    
    /**
     * @return ImagesGroupConfig[]
     */
    public function getImagesGroupsConfigurations(): array
    {
        return $this->getFilesGroupsConfigurations();
    }
    
    public function getImagesGroupConfiguration(string $name): ImagesGroupConfig
    {
        return $this->getFilesGroupConfiguration($name);
    }
    
    public function hasImagesGroupsConfigurations(): bool
    {
        return $this->hasFilesGroupsConfigurations();
    }
    
    public function hasImagesGroupConfiguration(string $name): bool
    {
        return $this->hasFilesGroupConfiguration($name);
    }
    
}