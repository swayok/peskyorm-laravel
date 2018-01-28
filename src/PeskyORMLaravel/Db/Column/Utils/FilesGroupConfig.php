<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\RecordInterface;

class FilesGroupConfig {

    const TXT = 'text/plain';
    const PDF = 'application/pdf';
    const RTF = 'application/rtf';
    const DOC = 'application/msword';
    const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    const XLS = 'application/ms-excel';
    const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    const PPT = 'application/vnd.ms-powerpoint';
    const PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    const CSV = 'text/csv';
    const PNG = 'image/png';
    const JPEG = 'image/jpeg';
    const GIF = 'image/gif';
    const SVG = 'image/svg';
    const ZIP = 'application/zip';
    const RAR = 'application/x-rar-compressed';
    const GZIP = 'application/gzip';
    const MP4_VIDEO = 'video/mp4';
    const MP4_AUDIO = 'audio/mp4';
    const UNKNOWN = 'application/octet-stream';

    const TYPE_FILE = 'file';
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_TEXT = 'text';
    const TYPE_ARCHIVE = 'archive';
    const TYPE_OFFICE = 'office';

    /**
     * @var array
     */
    protected $mimeToExt = [
        self::TXT => 'txt',
        self::PDF => 'pdf',
        self::RTF => 'rtf',
        self::DOC => 'doc',
        self::DOCX => 'docx',
        self::XLS => 'xls',
        self::XLSX => 'xlsx',
        self::PPT => 'ppt',
        self::PPTX => 'pptx',
        self::PNG => 'png',
        self::JPEG => 'jpg',
        self::GIF => 'gif',
        self::SVG => 'svg',
        self::MP4_VIDEO => 'mp4',
        self::MP4_AUDIO => 'mp3',
        self::CSV => 'csv',
        self::ZIP => 'zip',
        self::RAR => 'rar',
        self::GZIP => 'gzip',
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
    protected $allowedMimeTypes = [];
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
     * List of aliases for file types.
     * Format: 'common/filetype' => ['alias/filetype1', 'alias/filetype2']
     * For example: image/jpeg file type has alias image/x-jpeg
     * @var array
     */
    static protected $mimeTypeAliases = [
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
        ],
        self::ZIP => [
            'application/x-compressed',
            'application/x-zip-compressed',
            'multipart/x-zip'
        ],
        self::GZIP => [
            'application/x-gzip',
            'multipart/x-gzip',
        ]
    ];

    static protected $mimeTypeToFileType = [
        self::TXT => self::TYPE_TEXT,
        self::PDF => self::TYPE_OFFICE,
        self::RTF => self::TYPE_TEXT,
        self::DOC => self::TYPE_OFFICE,
        self::DOCX => self::TYPE_OFFICE,
        self::XLS => self::TYPE_OFFICE,
        self::XLSX => self::TYPE_OFFICE,
        self::CSV => self::TYPE_TEXT,
        self::PPT => self::TYPE_OFFICE,
        self::PPTX => self::TYPE_OFFICE,
        self::PNG => self::TYPE_IMAGE,
        self::JPEG => self::TYPE_IMAGE,
        self::GIF => self::TYPE_IMAGE,
        self::SVG => self::TYPE_IMAGE,
        self::MP4_VIDEO => self::TYPE_VIDEO,
        self::MP4_AUDIO => self::TYPE_AUDIO,
        self::ZIP => self::TYPE_ARCHIVE,
        self::RAR => self::TYPE_ARCHIVE,
        self::GZIP => self::TYPE_ARCHIVE,
    ];

    /**
     * @var null|\Closure
     */
    protected $fileNameBuilder;

    /**
     * @param string|null $mimeType
     * @return string
     */
    static public function detectFileTypeByMimeType($mimeType) {
        if (empty($mimeType) || !is_string($mimeType)) {
            return static::UNKNOWN;
        }
        $mimeType = mb_strtolower($mimeType);
        if (array_key_exists($mimeType, static::$mimeTypeToFileType)) {
            return static::$mimeTypeToFileType[$mimeType];
        }
        foreach (static::$mimeTypeAliases as $mime => $aliases) {
            if (in_array($mimeType, $aliases, true)) {
                return static::$mimeTypeToFileType[$mime];
            }
        }
        return static::UNKNOWN;
    }

    /**
     * @param string $name
     * @throws \InvalidArgumentException
     */
    public function __construct($name) {
        $this->name = $name;
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
     * @return array
     */
    public function getAllowedFileExtensions() {
        return array_values(array_intersect_key($this->mimeToExt, array_flip($this->getAllowedMimeTypes(false))));
    }

    /**
     * @param array $allowedMimeTypes
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAllowedMimeTypes(...$allowedMimeTypes) {
        if (count($allowedMimeTypes) === 1 && isset($allowedMimeTypes[0]) && is_array($allowedMimeTypes[0])) {
            $allowedMimeTypes = $allowedMimeTypes[0];
        }
        $this->allowedMimeTypesAliases = [];
        /** @var array $allowedMimeTypes */
        foreach ($allowedMimeTypes as $fileType) {
            if (!empty(static::$mimeTypeAliases[$fileType])) {
                $this->allowedMimeTypesAliases = array_merge($this->allowedMimeTypesAliases, (array)static::$mimeTypeAliases[$fileType]);
            }
        }
        $this->allowedMimeTypes = array_merge($allowedMimeTypes, $this->allowedMimeTypesAliases);
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
            $this->fileNameBuilder = function (FilesGroupConfig $fileConfig, $fileSuffix = null) {
                return $fileConfig->getName() . (string)$fileSuffix;
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