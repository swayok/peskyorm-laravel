<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\RecordInterface;

class FileConfig {

    const TXT = 'text/plain';
    const PDF = 'application/pdf';
    const RTF = 'application/rtf';
    const DOC = 'application/msword';
    const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    const XLS = 'application/ms-excel';
    const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    const CSV = 'text/csv';
    const PNG = 'image/png';
    const JPEG = 'image/jpeg';
    const GIF = 'image/gif';
    const SVG = 'image/svg';
    const MP4_VIDEO = 'video/mp4';
    const MP4_AUDIO = 'audio/mp4';

    /**
     * @var array
     */
    protected $typeToExt = [
        self::TXT => 'txt',
        self::PDF => 'pdf',
        self::RTF => 'rtf',
        self::DOC => 'doc',
        self::DOCX => 'docx',
        self::XLS => 'xls',
        self::XLSX => 'xlsx',
        self::PNG => 'png',
        self::JPEG => 'jpg',
        self::GIF => 'gif',
        self::SVG => 'svg',
        self::MP4_VIDEO => 'mp4',
        self::MP4_AUDIO => 'mp3',
        self::CSV => 'csv',
    ];

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
    protected $allowedFileTypes = [];
    /**
     * @var array
     */
    protected $defaultAllowedFileTypes = [
        self::TXT,
        self::PDF,
        self::RTF,
        self::DOC,
        self::DOCX,
        self::XLS,
        self::XLSX,
    ];

    /**
     * @var array
     */
    protected $allowedFileTypesAliases = [];

    /**
     * List of aliases for file types.
     * Format: 'common/filetype' => ['alias/filetype1', 'alias/filetype2']
     * For example: image/jpeg file type has alias image/x-jpeg
     * @var array
     */
    protected $fileTypeAliases = [
        self::JPEG => [
            'image/x-jpeg'
        ],
        self::PNG => [
            'image/x-png'
        ],
        self::RTF => [
            'application/x-rtf',
            'text/richtext'
        ],
        self::XLS => [
            'application/excel',
            'application/vnd.ms-excel',
            'application/x-excel',
            'application/x-msexcel',
        ]
    ];

    /**
     * @var null|\Closure
     */
    protected $fileNameBuilder;

    public function __construct($name) {
        $this->name = $name;
        $this->setAllowedFileTypes($this->defaultAllowedFileTypes);
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
    public function getAllowedFileTypes($withAliases = true) {
        return $withAliases ? $this->allowedFileTypes : array_diff($this->allowedFileTypes, $this->allowedFileTypesAliases);
    }

    /**
     * @return array
     */
    public function getAllowedFileExtensions() {
        return array_values(array_intersect_key($this->typeToExt, array_flip($this->getAllowedFileTypes(false))));
    }

    /**
     * @param array $allowedFileTypes
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAllowedFileTypes(...$allowedFileTypes) {
        if (count($allowedFileTypes) === 1 && isset($allowedFileTypes[0]) && is_array($allowedFileTypes[0])) {
            $allowedFileTypes = $allowedFileTypes[0];
        }
        $this->allowedFileTypesAliases = [];
        /** @var array $allowedFileTypes */
        foreach ($allowedFileTypes as $fileType) {
            if (!empty($this->fileTypeAliases[$fileType])) {
                $this->allowedFileTypesAliases = array_merge($this->allowedFileTypesAliases, (array)$this->fileTypeAliases[$fileType]);
            }
        }
        $this->allowedFileTypes = array_merge($allowedFileTypes, $this->allowedFileTypesAliases);
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxFilesCount() {
        return $this->maxFilesCount;
    }

    /**
     * @param int $count - 0 for unlimited
     * @return $this
     */
    public function setMaxFilesCount($count) {
        $this->maxFilesCount = max(1, (int)$count);
        return $this;
    }

    /**
     * @return int
     */
    public function getMinFilesCount() {
        return $this->minFilesCount;
    }

    /**
     * @param int $minFilesCount
     * @return $this
     */
    public function setMinFilesCount($minFilesCount) {
        $this->minFilesCount = max(0, (int)$minFilesCount);
        return $this;
    }

    /**
     * @return \Closure|null
     */
    protected function getFileNameBuilder() {
        if (!$this->fileNameBuilder) {
            $this->fileNameBuilder = function (FileConfig $fileConfig, $fileSuffix = null) {
                return $fileConfig->getName() . (string)$fileSuffix;
            };
        }
        return $this->fileNameBuilder;
    }

    /**
     * Function that will build a name for a new file (without extension)
     * @param \Closure $fileNameBuilder -
     *    function (FileConfig $fileConfig, $fileSuffix = null) { return $fileConfig->getName() . (string)$fileSuffix }
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
                'Value returned from FileConfig->fileNameBuilder must be a not empty string'
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
            'allowed_mime_types' => $this->getAllowedFileTypes(),
        ];
    }
}