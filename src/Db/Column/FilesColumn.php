<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Column;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;
use PeskyORMLaravel\Db\Column\Utils\FileConfig;
use PeskyORMLaravel\Db\Column\Utils\FilesGroupConfig;
use PeskyORMLaravel\Db\Column\Utils\FilesUploadingColumnClosures;
use PeskyORMLaravel\Db\Column\Utils\ImagesGroupConfig;
use PeskyORMLaravel\Db\KeyValueTableUtils\KeyValueTableInterface;

class FilesColumn extends Column implements \Iterator, \ArrayAccess
{
    
    /**
     * @var string
     */
    protected $defaultClosuresClass = FilesUploadingColumnClosures::class;
    /**
     * @var string
     */
    protected $fileConfigClass = FilesGroupConfig::class;
    /**
     * @var string
     */
    protected $relativeUploadsFolderPath;
    /**
     * @var ImagesGroupConfig[]|FilesGroupConfig[]|\Closure[]
     */
    protected $configs = [];
    /**
     * @var array
     */
    protected $iterator;
    
    public const IMAGE_TYPE_IS_NOT_ALLOWED = 'invalid_image_type';
    public const FILE_TYPE_IS_NOT_ALLOWED = 'invalid_file_type';
    public const FILE_SIZE_IS_TOO_LARGE = 'file_size_is_too_large';
    public const FILE_IS_NOT_A_VALID_IMAGE = 'file_is_not_a_valid_image';
    public const DATA_IS_NOT_A_VALID_UPLOAD_INFO = 'file_is_not_a_valid_upload';
    
    protected static $additionalValidationErrorsMessages = [
        self::IMAGE_TYPE_IS_NOT_ALLOWED => "Uploaded image type '%s' is not allowed for '%s'. Allowed file types: %s.",
        self::FILE_TYPE_IS_NOT_ALLOWED => "Uploaded file type '%s' is not allowed for '%s'. Allowed file types: %s.",
        self::FILE_SIZE_IS_TOO_LARGE => "Uploaded file size is too large for '%s'. Maximum file size is %s kilobytes.",
        self::FILE_IS_NOT_A_VALID_IMAGE => "Uploaded file for '%s' is corrupted or it is not a valid image.",
        self::DATA_IS_NOT_A_VALID_UPLOAD_INFO => "Data received for '%s' is not a valid upload.",
    ];
    
    /**
     * @param null|string $name
     * @param string|null $notUsed
     * @return static
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public static function create(?string $name = null, ?string $notUsed = null)
    {
        return new static($name);
    }
    
    /**
     * @param string|null $name
     */
    public function __construct(?string $name)
    {
        parent::__construct($name, static::TYPE_JSONB);
        $this
            ->convertsEmptyStringToNull()
            ->setDefaultValue('{}');
    }
    
    public function isItAFile(): bool
    {
        return true;
    }
    
    /**
     * Path to folder is relative to public_path()
     * @param string|\Closure $folder - function (RecordInterface $record, FilesGroupConfig $fileConfig) { return 'path/to/folder'; }
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
        if ($table instanceof KeyValueTableInterface) {
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
     * @param string $name - file field name
     * @param \Closure|null $configurator = function (FilesGroupConfig $fileConfig) { //modify $fileConfig }
     * @return static
     */
    public function addFilesGroupConfiguration(string $name, ?\Closure $configurator = null)
    {
        $this->configs[$name] = $configurator;
        $this->iterator = null;
        return $this;
    }
    
    /**
     * @return FilesGroupConfig[]|ImagesGroupConfig[]
     */
    public function getFilesGroupsConfigurations(): array
    {
        foreach ($this->configs as $name => $config) {
            if (!(get_class($config) === $this->fileConfigClass)) {
                $this->getFilesGroupConfiguration($name);
            }
        }
        return $this->configs;
    }
    
    /**
     * @param string $name
     * @return FilesGroupConfig|ImagesGroupConfig
     * @throws \UnexpectedValueException
     */
    public function getFilesGroupConfiguration(string $name)
    {
        if (!$this->hasFilesGroupConfiguration($name)) {
            throw new \UnexpectedValueException("There is no configuration for file called '$name'");
        } elseif (!is_object($this->configs[$name]) || get_class($this->configs[$name]) !== $this->fileConfigClass) {
            $class = $this->fileConfigClass;
            /** @var FilesGroupConfig $fileConfig */
            $fileConfig = new $class($name);
            $fileConfig
                ->setAbsolutePathToFileFolder(function (RecordInterface $record, FileConfig $fileConfig) {
                    return $this->getAbsoluteFileUploadsFolder($record, $fileConfig);
                })
                ->setRelativeUrlToFileFolder(function (RecordInterface $record, FileConfig $fileConfig) {
                    return $this->getRelativeFileUploadsUrl($record, $fileConfig);
                });
            if ($this->configs[$name] instanceof \Closure) {
                call_user_func($this->configs[$name], $fileConfig);
            }
            $this->configs[$name] = $fileConfig;
        }
        return $this->configs[$name];
    }
    
    public function hasFilesGroupConfiguration(string $name): bool
    {
        return array_key_exists($name, $this->configs);
    }
    
    public function hasFilesGroupsConfigurations(): bool
    {
        return !empty($this->configs);
    }
    
    public static function getValidationErrorsMessages(): array
    {
        return static::$validationErrorsMessages ?: array_merge(static::$additionalValidationErrorsMessages, parent::getValidationErrorsMessages());
    }
    
    public function getIterator(): \ArrayIterator
    {
        if ($this->iterator === null) {
            $this->iterator = new \ArrayIterator($this->configs);
        }
        return $this->iterator;
    }
    
    public function current()
    {
        return $this->getFilesGroupConfiguration(
            $this->getIterator()
                ->key()
        );
    }
    
    public function next(): void
    {
        $this->getIterator()
            ->next();
    }
    
    public function key()
    {
        return $this->getIterator()
            ->key();
    }
    
    public function valid(): bool
    {
        return $this->getIterator()
            ->valid();
    }
    
    public function rewind(): void
    {
        $this->getIterator()
            ->rewind();
    }
    
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->configs);
    }
    
    public function offsetGet($offset)
    {
        return $this->getFilesGroupConfiguration($offset);
    }
    
    public function offsetSet($offset, $value): void
    {
        throw new \BadMethodCallException('You must use special setter method add*Configuration()');
    }
    
    public function offsetUnset($offset): void
    {
        throw new \BadMethodCallException('Removing file configuration is forbidden');
    }
    
}
