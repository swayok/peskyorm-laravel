<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\RecordInterface;

interface FileConfigInterface {

    public function __construct($name, bool $isRequired = false);

    public function setAbsolutePathToFileFolder(\Closure $pathBuilder);

    public function getAbsolutePathToFileFolder(RecordInterface $record);

    public function setRelativeUrlToFileFolder(\Closure $relativeUrlBuilder);

    public function getRelativeUrlToFileFolder(RecordInterface $record);

    public function getName();

    public function getMaxFileSize();

    public function setMaxFileSize($maxFileSize);

    public function getAllowedMimeTypes($withAliases = true);

    public function setAllowedMimeTypes(...$allowedMimeTypes);

    public function setAllowedFileExtensions(... $allowedFileExtenstions);

    public function getAllowedFileExtensions();

    public function getMaxFilesCount();

    public function requireFile();

    public function getMinFilesCount();

    public function setFileNameBuilder(\Closure $fileNameBuilder);

    public function makeNewFileName($fileSuffix = null);

    public function getConfigsArrayForJs();

}