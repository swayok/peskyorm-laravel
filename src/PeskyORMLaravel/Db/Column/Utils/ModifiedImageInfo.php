<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\RecordInterface;

class ModifiedImageInfo extends FileInfo {

    protected $filePath;

    /**
     * @param \SplFileInfo $fileInfo
     * @param FilesGroupConfig $fileConfig
     * @param RecordInterface $record
     * @param null|string $fileSuffix
     * @return ModifiedImageInfo
     */
    static public function fromSplFileInfo(\SplFileInfo $fileInfo, FilesGroupConfig $fileConfig, RecordInterface $record, $fileSuffix = null) {
        /** @var ModifiedImageInfo $obj */
        $obj = parent::fromSplFileInfo($fileInfo, $fileConfig, $record, $fileSuffix);
        $obj->setFilePath($fileInfo->getRealPath());
        return $obj;
    }

    protected function setFilePath($path) {
        $this->filePath = $path;
        return $this;
    }

    public function getFileName() {
        return $this->getOriginalFileName();
    }

    public function getFileNameWithExtension() {
        return $this->getOriginalFileNameWithExtension();
    }

    public function getAbsoluteFilePath() {
        return $this->filePath;
    }

    public function getRelativeUrl() {
        $path = preg_replace('%\\\+%', '/', $this->getAbsoluteFilePath());
        $relativeUrl = str_ireplace(preg_replace('%\\\+%', '/', public_path()), '', $path);
        $relativeUrl = str_ireplace(preg_replace('%\\\+%', '/', app_path()), '', $relativeUrl);
        return $relativeUrl;
    }
}