<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORMLaravel\Db\Column\Utils\AsyncImagesUploadingColumnClosures;

class AsyncImagesColumn extends ImagesColumn {

    protected $defaultClosuresClass = AsyncImagesUploadingColumnClosures::class;

}
