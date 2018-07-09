<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORMLaravel\Db\Column\Utils\ImageConfig;
use PeskyORMLaravel\Db\Column\Utils\ImagesUploadingColumnClosures;

class ImageColumn extends FileColumn {

    protected $defaultClosuresClass = ImagesUploadingColumnClosures::class;
    protected $fileConfigClass = ImageConfig::class;

    public function isItAnImage() {
        return true;
    }

}