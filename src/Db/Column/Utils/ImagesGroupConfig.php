<?php

namespace PeskyORMLaravel\Db\Column\Utils;

class ImagesGroupConfig extends ImageConfig implements FilesGroupConfigInterface {

    /**
     * @param int $count - 0 for unlimited
     * @return $this
     */
    public function setMaxFilesCount($count) {
        $this->maxFilesCount = max(1, (int)$count);
        return $this;
    }

    /**
     * @param int $minFilesCount
     * @return $this
     */
    public function setMinFilesCount($minFilesCount) {
        $this->minFilesCount = max(0, (int)$minFilesCount);
        return $this;
    }

}