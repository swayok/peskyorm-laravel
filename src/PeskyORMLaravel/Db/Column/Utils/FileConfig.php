<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\RecordInterface;

class FileConfig extends MimeTypesHelper {

    /** @var string */
    protected $name;
    /** @var string */
    protected $absolutePathToFileFolder;
    /** @var string */
    protected $relativeUrlToFileFolder;
    /** @var int */
    protected $minFilesCount = 0;
    /** @var int */
    protected $maxFilesCount = 1;
    /**
     * In kilobytes
     * @var int
     */
    protected $maxFileSize = 20480;
    /**
     * @var array
     */
    protected $allowedMimeTypes = [];
    /**
     * @var array|null
     */
    protected $allowedFileExtensions = null;
    /**
     * @var array
     */
    protected $defaultAllowedMimeTypes = [
        self::TXT,
        self::PDF,
        self::RTF,
        self::DOC,
        self::DOCX,
        self::XLS,
        self::XLSX,
        self::PPT,
        self::PPTX,
        self::ZIP,
        self::RAR,
        self::GZIP,
        self::PNG,
        self::JPEG,
        self::SVG,
        self::GIF,
    ];

    /**
     * @var array
     */
    protected $allowedMimeTypesAliases = [];

    /**
     * @var null|\Closure
     */
    protected $fileNameBuilder;

    /**
     * @param string $name
     * @param bool $isRequired
     * @throws \InvalidArgumentException
     */
    public function __construct($name, bool $isRequired = false) {
        $this->name = $name;
        $this->minFilesCount = $isRequired ? 1 : 0;
        $this->setAllowedMimeTypes($this->defaultAllowedMimeTypes);
    }

    /**
     * @param \Closure $pathBuilder - function (RecordInterface $record) { return '/var/www/site/public/table_name/column_name' }
     * @return $this
     */
    public function setAbsolutePathToFileFolder(\Closure $pathBuilder) {
        $this->absolutePathToFileFolder = $pathBuilder;
        return $this;
    }

    /**
     * @param RecordInterface $record
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getAbsolutePathToFileFolder(RecordInterface $record) {
        if (empty($this->absolutePathToFileFolder)) {
            throw new \UnexpectedValueException('Absolute path to file folder is not set');
        }
        return call_user_func($this->absolutePathToFileFolder, $record, $this);
    }

    /**
     * Builder returns relatiove url to folder where all images are
     * @param \Closure $relativeUrlBuilder - function (RecordInterface $record) { return '/assets/sub/' . $record->getPrimaryKeyValue(); }
     * @return $this
     */
    public function setRelativeUrlToFileFolder(\Closure $relativeUrlBuilder) {
        $this->relativeUrlToFileFolder = $relativeUrlBuilder;
        return $this;
    }

    /**
     * @param RecordInterface $record
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getRelativeUrlToFileFolder(RecordInterface $record) {
        if (empty($this->relativeUrlToFileFolder)) {
            throw new \UnexpectedValueException('Relative url to file folder is not set');
        }
        return call_user_func($this->relativeUrlToFileFolder, $record, $this);
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getMaxFileSize() {
        return $this->maxFileSize;
    }

    /**
     * @param int $maxFileSize - in kilobytes
     * @return $this
     */
    public function setMaxFileSize($maxFileSize) {
        $this->maxFileSize = (int)$maxFileSize;
        return $this;
    }

    /**
     * @param bool $withAliases
     * @return array
     */
    public function getAllowedMimeTypes($withAliases = true) {
        return $withAliases ? $this->allowedMimeTypes : array_diff($this->allowedMimeTypes, $this->allowedMimeTypesAliases);
    }

    /**
     * @param array $allowedMimeTypes
     * @return $this
     */
    public function setAllowedMimeTypes(...$allowedMimeTypes) {
        if (count($allowedMimeTypes) === 1 && isset($allowedMimeTypes[0]) && is_array($allowedMimeTypes[0])) {
            $allowedMimeTypes = $allowedMimeTypes[0];
        }
        $this->allowedMimeTypesAliases = static::getAliasesForMimeTypes($allowedMimeTypes);
        $this->allowedMimeTypes = array_merge($allowedMimeTypes, $this->allowedMimeTypesAliases);
        return $this;
    }

    /**
     * @param array $allowedFileExtenstions
     * @return $this
     */
    public function setAllowedFileExtensions(... $allowedFileExtenstions) {
        if (count($allowedFileExtenstions) === 1 && isset($allowedFileExtenstions[0]) && is_array($allowedFileExtenstions[0])) {
            $allowedFileExtenstions = $allowedFileExtenstions[0];
        }
        $this->allowedFileExtensions = $allowedFileExtenstions;
        return $this;
    }

    /**
     * @return array
     */
    public function getAllowedFileExtensions() {
        if ($this->allowedFileExtensions === null) {
            return array_values(array_intersect_key(static::$mimeToExt, array_flip($this->getAllowedMimeTypes(false))));
        } else {
            return $this->allowedFileExtensions;
        }
    }

    /**
     * @return int
     */
    public function getMaxFilesCount() {
        return $this->maxFilesCount;
    }

    /**
     * File must be uploaded
     * @return $this
     */
    public function requireFile() {
        $this->minFilesCount = 1;
        return $this;
    }

    /**
     * @return int
     */
    public function getMinFilesCount() {
        return $this->minFilesCount;
    }

    /**
     * @return \Closure|null
     */
    protected function getFileNameBuilder() {
        if (!$this->fileNameBuilder) {
            $this->fileNameBuilder = function ($fileConfig, $fileSuffix = null) {
                /** @var FileConfig|FilesGroupConfig $fileConfig */
                return $fileConfig->getName() . $fileSuffix;
            };
        }
        return $this->fileNameBuilder;
    }

    /**
     * Function that will build a name for a new file (without extension)
     * @param \Closure $fileNameBuilder -
     *    function (FilesGroupConfig $fileConfig, $fileSuffix = null) { return $fileConfig->getName() . (string)$fileSuffix }
     * @return $this
     */
    public function setFileNameBuilder(\Closure $fileNameBuilder) {
        $this->fileNameBuilder = $fileNameBuilder;
        return $this;
    }

    /**
     * @param null|int|string $fileSuffix
     * @return string
     * @throws \UnexpectedValueException
     */
    public function makeNewFileName($fileSuffix = null) {
        $fileName = call_user_func($this->getFileNameBuilder(), $this, $fileSuffix);
        if (empty($fileName) || !is_string($fileName)) {
            throw new \UnexpectedValueException(
                'Value returned from FilesGroupConfig->fileNameBuilder must be a not empty string'
            );
        }
        return $fileName;
    }

    /**
     * @return array
     */
    public function getConfigsArrayForJs() {
        return [
            'min_files_count' => $this->getMinFilesCount(),
            'max_files_count' => $this->getMaxFilesCount(),
            'max_file_size' => $this->getMaxFileSize(),
            'allowed_extensions' => $this->getAllowedFileExtensions(),
            'allowed_mime_types' => $this->getAllowedMimeTypes(),
        ];
    }
}