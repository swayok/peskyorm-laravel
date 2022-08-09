<?php

namespace PeskyORMLaravel\Db\Column\Utils;

interface FilesGroupConfigInterface extends FileConfigInterface {

    public function setMaxFilesCount($count);

    public function setMinFilesCount($minFilesCount);
}