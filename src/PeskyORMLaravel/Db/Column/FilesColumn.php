<?php

namespace PeskyORMLaravel\Db\Column;

use PeskyORM\ORM\Column;
use PeskyORM\ORM\RecordInterface;
use PeskyORMLaravel\Db\Column\Utils\FilesGroupConfig;
use PeskyORMLaravel\Db\Column\Utils\FilesUploadingColumnClosures;
use PeskyORMLaravel\Db\Column\Utils\ImagesGroupConfig;
use PeskyORMLaravel\Db\KeyValueTableUtils\KeyValueTableInterface;

class FilesColumn extends Column implements \Iterator, \ArrayAccess {

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
     * @param null $notUsed
     * @return static
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function create($name = null, $notUsed = null) {
        return new static($name);
    }

    /**
     * @param string|null $name
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __construct($name) {
        parent::__construct($name, static::TYPE_JSONB);
        $this
            ->convertsEmptyStringToNull()
            ->setDefaultValue('{}');
    }

    /**
     * @return bool
     */
    public function isItAFile() {
        return true;
    }

    /**
     * Path to folder is relative to public_path()
     * @param string|\Closure $folder - function (RecordInterface $record, FilesGroupConfig $fileConfig) { return 'path/to/folder'; }
     * @return $this
     */
    public function setRelativeUploadsFolderPath($folder) {
        $this->relativeUploadsFolderPath = static::normalizeFolderPath($folder);
        return $this;
    }

    /**
     * @param RecordInterface $record
     * @param FilesGroupConfig $fileConfig
     * @return string
     */
    protected function getRelativeUploadsFolderPath(RecordInterface $record, FilesGroupConfig $fileConfig) {
        if ($this->relativeUploadsFolderPath instanceof \Closure) {
            return call_user_func($this->relativeUploadsFolderPath, $record, $fileConfig);
        } else {
            return $this->buildRelativeUploadsFolderPathForRecordAndFileConfig($record, $fileConfig);
        }
    }

    /**
     * @param RecordInterface $record
     * @param FilesGroupConfig $fileConfig
     * @return string
     */
    protected function buildRelativeUploadsFolderPathForRecordAndFileConfig(RecordInterface $record, FilesGroupConfig $fileConfig) {
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
     * @param FilesGroupConfig $fileConfig
     * @return string
     */
    public function getAbsoluteFileUploadsFolder(RecordInterface $record, FilesGroupConfig $fileConfig) {
        return static::normalizeFolderPath(public_path($this->getRelativeUploadsFolderPath($record, $fileConfig)));
    }

    /**
     * @param RecordInterface $record
     * @param FilesGroupConfig $fileConfig
     * @return string
     */
    public function getRelativeFileUploadsUrl(RecordInterface $record, FilesGroupConfig $fileConfig) {
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
     * @param string $name - file field name
     * @param \Closure $configurator = function (FilesGroupConfig $fileConfig) { //modify $fileConfig }
     * @return $this
     */
    public function addFilesGroupConfiguration($name, \Closure $configurator = null) {
        $this->configs[$name] = $configurator;
        $this->iterator = null;
        return $this;
    }

    /**
     * @return FilesGroupConfig[]|ImagesGroupConfig[]
     * @throws \UnexpectedValueException
     */
    public function getFilesGroupsConfigurations() {
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
    public function getFilesGroupConfiguration($name) {
        if (!$this->hasFilesGroupConfiguration($name)) {
            throw new \UnexpectedValueException("There is no configuration for file called '$name'");
        } else if (!is_object($this->configs[$name]) || get_class($this->configs[$name]) !== $this->fileConfigClass) {
            $class = $this->fileConfigClass;
            /** @var FilesGroupConfig $fileConfig */
            $fileConfig = new $class($name);
            $fileConfig
                ->setAbsolutePathToFileFolder(function (RecordInterface $record, FilesGroupConfig $fileConfig) {
                    return $this->getAbsoluteFileUploadsFolder($record, $fileConfig);
                })
                ->setRelativeUrlToFileFolder(function (RecordInterface $record, FilesGroupConfig $fileConfig) {
                    return $this->getRelativeFileUploadsUrl($record, $fileConfig);
                });
            if ($this->configs[$name] instanceof \Closure) {
                call_user_func($this->configs[$name], $fileConfig);
            }
            $this->configs[$name] = $fileConfig;
        }
        return $this->configs[$name];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasFilesGroupConfiguration($name) {
        return array_key_exists($name, $this->configs);
    }

    /**
     * @return bool
     */
    public function hasFilesGroupsConfigurations() {
        return !empty($this->configs);
    }

    /**
     * @return array
     */
    static public function getValidationErrorsMessages() {
        return static::$validationErrorsMessages ?: array_merge(static::$additionalValidationErrorsMessages, parent::getValidationErrorsMessages());
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator() {
        if ($this->iterator === null) {
            $this->iterator = new \ArrayIterator($this->configs);
        }
        return $this->iterator;
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return ImagesGroupConfig|FilesGroupConfig
     * @throws \UnexpectedValueException
     * @since 5.0.0
     */
    public function current() {
        return $this->getFilesGroupConfiguration($this->getIterator()->key());
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next() {
        $this->getIterator()->next();
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key() {
        return $this->getIterator()->key();
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid() {
        return $this->getIterator()->valid();
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind() {
        $this->getIterator()->rewind();
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset) {
        return array_key_exists($offset, $this->configs);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return FilesGroupConfig
     * @throws \UnexpectedValueException
     * @since 5.0.0
     */
    public function offsetGet($offset) {
        return $this->getFilesGroupConfiguration($offset);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @throws \BadMethodCallException
     * @since 5.0.0
     */
    public function offsetSet($offset, $value) {
        throw new \BadMethodCallException('You must use special setter method add*Configuration()');
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @throws \BadMethodCallException
     * @since 5.0.0
     */
    public function offsetUnset($offset) {
        throw new \BadMethodCallException('Removing file configuration is forbidden');
    }

}