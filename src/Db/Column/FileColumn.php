<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Column;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;
use PeskyORMLaravel\Db\Column\Utils\FileConfig;
use PeskyORMLaravel\Db\Column\Utils\FileUploadingColumnClosures;
use PeskyORMLaravel\Db\Column\Utils\ImageConfig;
use PeskyORMLaravel\Db\LaravelKeyValueTableHelpers\LaravelKeyValueTableInterface;

class FileColumn extends Column
{
    
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
    
    public const IMAGE_TYPE_IS_NOT_ALLOWED = 'invalid_image_type';
    public const FILE_TYPE_IS_NOT_ALLOWED = 'invalid_file_type';
    public const FILE_SIZE_IS_TOO_LARGE = 'file_size_is_too_large';
    public const FILE_IS_NOT_A_VALID_IMAGE = 'file_is_not_a_valid_image';
    
    protected static $additionalValidationErrorsMessages = [
        self::IMAGE_TYPE_IS_NOT_ALLOWED => "Uploaded image type '%s' is not allowed for '%s'. Allowed file types: %s.",
        self::FILE_TYPE_IS_NOT_ALLOWED => "Uploaded file type '%s' is not allowed for '%s'. Allowed file types: %s.",
        self::FILE_SIZE_IS_TOO_LARGE => "Uploaded file size is too large for '%s'. Maximum file size is %s kilobytes.",
        self::FILE_IS_NOT_A_VALID_IMAGE => "Uploaded file for '%s' is corrupted or it is not a valid image.",
    ];
    
    /**
     * @param null|string $name
     * @param null|string|\Closure $relativeUploadsFolderPath
     * @return static
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public static function create($relativeUploadsFolderPath, ?string $name = null)
    {
        return new static($name, $relativeUploadsFolderPath);
    }
    
    /**
     * @param string|null $name
     * @param null|string|\Closure $relativeUploadsFolderPath
     * @throws \InvalidArgumentException
     */
    public function __construct(?string $name, $relativeUploadsFolderPath)
    {
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
    
    public function isItAFile(): bool
    {
        return true;
    }
    
    /**
     * Path to folder is relative to public_path()
     * @param string|\Closure $folder - function (RecordInterface $record, FileConfig $fileConfig) { return 'path/to/folder'; }
     * @return static
     */
    public function setRelativeUploadsFolderPath($folder)
    {
        $this->relativeUploadsFolderPath = $folder instanceof \Closure ? $folder : static::normalizeFolderPath($folder);
        return $this;
    }
    
    protected function getRelativeUploadsFolderPath(RecordInterface $record, FileConfig $fileConfig): string
    {
        if ($this->relativeUploadsFolderPath instanceof \Closure) {
            return static::normalizeFolderPath(call_user_func($this->relativeUploadsFolderPath, $record, $fileConfig));
        } else {
            return $this->buildRelativeUploadsFolderPathForRecordAndFileConfig($record, $fileConfig);
        }
    }
    
    protected function buildRelativeUploadsFolderPathForRecordAndFileConfig(RecordInterface $record, FileConfig $fileConfig): string
    {
        $table = $record::getTable();
        if ($table instanceof LaravelKeyValueTableInterface) {
            $fkName = $table->getMainForeignKeyColumnName();
            $subfolder = empty($fkName) ? '' : $record->getValue($fkName);
        } else {
            $subfolder = $record->getPrimaryKeyValue();
        }
        $subfolder = preg_replace('%[^a-zA-Z0-9_-]+%', '_', $subfolder);
        return $this->relativeUploadsFolderPath . DIRECTORY_SEPARATOR . $subfolder . DIRECTORY_SEPARATOR . $fileConfig->getName();
    }
    
    public function getAbsoluteFileUploadsFolder(RecordInterface $record, FileConfig $fileConfig): string
    {
        return static::normalizeFolderPath(public_path($this->getRelativeUploadsFolderPath($record, $fileConfig)));
    }
    
    public function getRelativeFileUploadsUrl(RecordInterface $record, FileConfig $fileConfig): string
    {
        return static::normalizeFolderUrl($this->getRelativeUploadsFolderPath($record, $fileConfig));
    }
    
    protected static function normalizeFolderPath(string $path): string
    {
        return preg_replace('%[/\\\]+%', DIRECTORY_SEPARATOR, rtrim($path, ' /\\')) . DIRECTORY_SEPARATOR;
    }
    
    protected static function normalizeFolderUrl(string $url): string
    {
        return '/' . trim(preg_replace('%[\\\]+%', '/', $url), ' /') . '/';
    }
    
    /**
     * @param \Closure|null $configurator = function (FileConfig $fileConfig) { //modify $fileConfig }
     * @return static
     */
    public function setConfiguration(?\Closure $configurator = null)
    {
        $this->config = $configurator;
        return $this;
    }
    
    /**
     * @return FileConfig|ImageConfig
     * @throws \UnexpectedValueException
     */
    public function getConfiguration()
    {
        if (!$this->hasConfiguration()) {
            throw new \UnexpectedValueException('There is no configuration for a files group');
        } elseif (!is_object($this->config) || get_class($this->config) !== $this->fileConfigClass) {
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
    
    public function hasConfiguration(): bool
    {
        return !empty($this->config);
    }
    
    public static function getValidationErrorsMessages(): array
    {
        return static::$validationErrorsMessages ?: array_merge(static::$additionalValidationErrorsMessages, parent::getValidationErrorsMessages());
    }
    
}