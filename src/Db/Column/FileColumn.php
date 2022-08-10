<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;
use PeskyORMLaravel\Db\Column\Utils\FileConfig;
use PeskyORMLaravel\Db\Column\Utils\FileUploadingColumnClosures;
use PeskyORMLaravel\Db\Column\Utils\ImageConfig;
use PeskyORMLaravel\Db\KeyValueTableUtils\KeyValueTableInterface;

class FileColumn extends Column {

    /**
     * @var string
     */
    protected $defaultClosuresClass = FileUploadingColumnClosures::class;
    /**
     * @var string
     */
    protected $fileConfigClass = FileConfig::class;
    /**
     * @var string
     */
    protected $relativeUploadsFolderPath;
    /**
     * @var ImageConfig|FileConfig|\Closure
     */
    protected $config;

    const IMAGE_TYPE_IS_NOT_ALLOWED = 'invalid_image_type';
    const FILE_TYPE_IS_NOT_ALLOWED = 'invalid_file_type';
    const FILE_SIZE_IS_TOO_LARGE = 'file_size_is_too_large';
    const FILE_IS_NOT_A_VALID_IMAGE = 'file_is_not_a_valid_image';

    static protected $additionalValidationErrorsMessages = [
        self::IMAGE_TYPE_IS_NOT_ALLOWED => "Uploaded image type '%s' is not allowed for '%s'. Allowed file types: %s.",
        self::FILE_TYPE_IS_NOT_ALLOWED => "Uploaded file type '%s' is not allowed for '%s'. Allowed file types: %s.",
        self::FILE_SIZE_IS_TOO_LARGE => "Uploaded file size is too large for '%s'. Maximum file size is %s kilobytes.",
        self::FILE_IS_NOT_A_VALID_IMAGE => "Uploaded file for '%s' is corrupted or it is not a valid image.",
    ];

    /**
     * @param null|string $name
     * @param null|string|\Closure $relativeUploadsFolderPath
     * @return static
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public static function create($relativeUploadsFolderPath, $name = null) {
        return new static($name, $relativeUploadsFolderPath);
    }

    /**
     * @param string|null $name
     * @param null|string|\Closure $relativeUploadsFolderPath
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __construct($name, $relativeUploadsFolderPath) {
        parent::__construct($name, static::TYPE_JSONB);
        $this
            ->convertsEmptyStringToNull()
            ->setDefaultValue('{}');
        if ($relativeUploadsFolderPath) {
            if (!is_string($relativeUploadsFolderPath) && !($relativeUploadsFolderPath instanceof \Closure)) {
                throw new \InvalidArgumentException('$relativeUploadsFolderPath argument must be a string or \Closure');
            }
            $this->setRelativeUploadsFolderPath($relativeUploadsFolderPath);
        }
    }

    /**
     * @return bool
     */
    public function isItAFile() {
        return true;
    }

    /**
     * Path to folder is relative to public_path()
     * @param string|\Closure $folder - function (RecordInterface $record, FileConfig $fileConfig) { return 'path/to/folder'; }
     * @return $this
     */
    public function setRelativeUploadsFolderPath($folder) {
        $this->relativeUploadsFolderPath = $folder instanceof \Closure ? $folder : static::normalizeFolderPath($folder);
        return $this;
    }

    /**
     * @param RecordInterface $record
     * @param FileConfig $fileConfig
     * @return string
     */
    protected function getRelativeUploadsFolderPath(RecordInterface $record, FileConfig $fileConfig) {
        if ($this->relativeUploadsFolderPath instanceof \Closure) {
            return static::normalizeFolderPath(call_user_func($this->relativeUploadsFolderPath, $record, $fileConfig));
        } else {
            return $this->buildRelativeUploadsFolderPathForRecordAndFileConfig($record, $fileConfig);
        }
    }

    /**
     * @param RecordInterface $record
     * @param FileConfig $fileConfig
     * @return string
     */
    protected function buildRelativeUploadsFolderPathForRecordAndFileConfig(RecordInterface $record, FileConfig $fileConfig) {
        $table = $record::getTable();
        if ($table instanceof KeyValueTableInterface) {
            $fkName = $table->getMainForeignKeyColumnName();
            $subfolder = empty($fkName) ? '' : $record->getValue($fkName);
        } else {
            $subfolder = $record->getPrimaryKeyValue();
        }
        $subfolder = preg_replace('%[^a-zA-Z0-9_-]+%', '_', $subfolder);
        return $this->relativeUploadsFolderPath . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR . $fileConfig->getName();
    }

    /**
     * @param RecordInterface $record
     * @param FileConfig $fileConfig
     * @return string
     */
    public function getAbsoluteFileUploadsFolder(RecordInterface $record, FileConfig $fileConfig) {
        return static::normalizeFolderPath(public_path($this->getRelativeUploadsFolderPath($record, $fileConfig)));
    }

    /**
     * @param RecordInterface $record
     * @param FileConfig $fileConfig
     * @return string
     */
    public function getRelativeFileUploadsUrl(RecordInterface $record, FileConfig $fileConfig) {
        return static::normalizeFolderUrl($this->getRelativeUploadsFolderPath($record, $fileConfig));
    }

    /**
     * @param string $path
     * @return string
     */
    static protected function normalizeFolderPath($path) {
        return preg_replace('%[/\\\]+%', DIRECTORY_SEPARATOR, rtrim($path, ' /\\')) . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $url
     * @return string
     */
    static protected function normalizeFolderUrl($url) {
        return '/' . trim(preg_replace('%[\\\]+%', '/', $url), ' /') . '/';
    }

    /**
     * @param \Closure $configurator = function (FileConfig $fileConfig) { //modify $fileConfig }
     * @return $this
     */
    public function setConfiguration(\Closure $configurator = null) {
        $this->config = $configurator;
        return $this;
    }

    /**
     * @return FileConfig|ImageConfig
     * @throws \UnexpectedValueException
     */
    public function getConfiguration() {
        if (!$this->hasConfiguration()) {
            throw new \UnexpectedValueException('There is no configuration for a files group');
        } else if (!is_object($this->config) || get_class($this->config) !== $this->fileConfigClass) {
            $class = $this->fileConfigClass;
            /** @var FileConfig $fileConfig */
            $fileConfig = new $class($this->getName());
            $fileConfig
                ->setAbsolutePathToFileFolder(function (RecordInterface $record, FileConfig $fileConfig) {
                    return $this->getAbsoluteFileUploadsFolder($record, $fileConfig);
                })
                ->setRelativeUrlToFileFolder(function (RecordInterface $record, FileConfig $fileConfig) {
                    return $this->getRelativeFileUploadsUrl($record, $fileConfig);
                });
            if ($this->config instanceof \Closure) {
                call_user_func($this->config, $fileConfig);
            }
            $this->config = $fileConfig;
        }
        return $this->config;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasConfiguration() {
        return !empty($this->config);
    }

    /**
     * @return array
     */
    public static function getValidationErrorsMessages() {
        return static::$validationErrorsMessages ?: array_merge(static::$additionalValidationErrorsMessages, parent::getValidationErrorsMessages());
    }

}