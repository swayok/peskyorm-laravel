<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORMLaravel\Db\Column\Utils\ImageConfig;
use PeskyORMLaravel\Db\Column\Utils\ImageUploadingColumnClosures;

class ImageColumn extends FileColumn {

    protected $defaultClosuresClass = ImageUploadingColumnClosures::class;
    protected $fileConfigClass = ImageConfig::class;

    public function isItAnImage() {
        return true;
    }

}