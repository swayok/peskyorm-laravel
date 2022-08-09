<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORMLaravel\Db\Column\Utils\AsyncFilesUploadingColumnClosures;

class AsyncFilesColumn extends FilesColumn {

    /**
     * @var string
     */
    protected $defaultClosuresClass = AsyncFilesUploadingColumnClosures::class;


}
