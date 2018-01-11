<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyORM\ORM\RecordInterface;
use Ramsey\Uuid\Uuid;
use Swayok\Utils\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileInfo {

    /** @var FileConfig|ImageConfig */
    protected $fileConfig;
    /** @var int|string */
    protected $record;
    /** @var string */
    protected $fileName;
    /** @var string */
    protected $originalFileName;
    /** @var null|int|string */
    protected $fileSuffix;
    /** @var string */
    protected $fileExtension;
    /** @var string */
    protected $uuid;
    /** @var string */
    protected $uuidFoDb;
    /** @var array */
    protected $customInfo = [];
    /** @var null|int */
    protected $position;
    /** @var int */
    static private $autoPositioningCounter = 1;

    /**
     * @param array $fileInfo
     * @param FileConfig|ImageConfig $fileConfig
     * @param RecordInterface $record
     * @return static
     */
    static public function fromArray(array $fileInfo, FileConfig $fileConfig, RecordInterface $record) {
        /** @var FileInfo $obj */
        $obj = new static($fileConfig, $record, array_get($fileInfo, 'suffix'));
        $obj
            ->setFileName(array_get($fileInfo, 'name'))
            ->setOriginalFileName(array_get($fileInfo, 'original_name'))
            ->setFileExtension(array_get($fileInfo, 'extension'))
            ->setCustomInfo(array_get($fileInfo, 'info'))
            ->setUuid(array_get($fileInfo, 'uuid', function () use ($obj) {
                return $obj->makeTempUuid();
            }));
        if (array_has($fileInfo, 'position')) {
            $obj->setPosition($fileInfo['position']);
        }
        return $obj;
    }

    /**
     * @param \SplFileInfo $fileInfo
     * @param FileConfig|ImageConfig $fileConfig
     * @param RecordInterface $record
     * @param null|int $fileSuffix
     * @return static
     */
    static public function fromSplFileInfo(\SplFileInfo $fileInfo, FileConfig $fileConfig, RecordInterface $record, $fileSuffix = null) {
        $obj = new static($fileConfig, $record, $fileSuffix);
        if ($fileInfo instanceof UploadedFile) {
            $extension = $fileInfo->getClientOriginalExtension();
            $fileName = $fileInfo->getClientOriginalName();
        } else {
            $extension = $fileInfo->getExtension();
            $fileName = $fileInfo->getFilename();
        }
        $obj
            ->setFileExtension($extension)
            ->setOriginalFileName(preg_replace("%\.{$extension}$%", '', $fileName))
            ->setUuid($obj->makeUuid());
        return $obj;
    }

    /**
     * @param FileConfig $fileConfig
     * @param RecordInterface $record
     * @param null|int $fileSuffix
     */
    protected function __construct(FileConfig $fileConfig, RecordInterface $record, $fileSuffix = null) {
        $this->fileConfig = $fileConfig;
        $this->record = $record;
        $this->fileSuffix = $fileSuffix;
    }

    /**
     * @return string
     */
    public function getUuid() {
        return $this->uuid;
    }

    /**
     * Get UUID to be saved to DB
     * Unlike getUuid() this method will never return 'temporary UUID' for cases when UUID was not provided
     * during object creation via static::fromArray() method
     * @return string
     */
    protected function getUuidForDb() {
        if (empty($this->uuidFoDb)) {
            $this->uuidFoDb = $this->isTempUuid() ? $this->makeUuid() : $this->getUuid();
        }
        return $this->uuidFoDb;
    }

    /**
     * @return string
     */
    protected function makeUuid() {
        return Uuid::uuid4()->getHex();
    }

    /**
     * @return string
     */
    protected function makeTempUuid() {
        return 'hash:' . sha1($this->getAbsoluteFilePath() . $this->getOriginalFileNameWithExtension());
    }

    /**
     * @return bool
     */
    protected function isTempUuid() {
        return stripos($this->getUuid(), 'hash:') === 0;
    }

    /**
     * @param string $uuid
     * @return $this
     */
    protected function setUuid($uuid) {
        $this->uuid = $uuid;
        return $this;
    }

    /**
     * @return int|null|string
     */
    public function getFileSuffix() {
        return $this->fileSuffix;
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getFileName() {
        if (!$this->fileName) {
            $this->fileName = $this->fileConfig->makeNewFileName($this->getFileSuffix());
        }
        return $this->fileName;
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getFileNameWithExtension() {
        return rtrim($this->getFileName() . '.' . $this->getFileExtension(), '.');
    }

    /**
     * @param string $fileName
     * @return $this
     */
    protected function setFileName($fileName) {
        if (!empty($fileName)) {
            $this->fileName = $fileName;
        }
        return $this;
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getOriginalFileName() {
        if (!$this->originalFileName) {
            $this->originalFileName = $this->getFileName();
        }
        return $this->originalFileName;
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getOriginalFileNameWithExtension() {
        return rtrim($this->getOriginalFileName() . '.' . $this->getFileExtension(), '.');
    }

    /**
     * @param string $fileNameWithoutExtension
     * @return $this
     */
    protected function setOriginalFileName($fileNameWithoutExtension) {
        $this->originalFileName = $fileNameWithoutExtension;
        return $this;
    }

    /**
     * @return string
     */
    public function getFileExtension() {
        return $this->fileExtension;
    }

    /**
     * @param string $fileExtension
     * @return $this
     */
    protected function setFileExtension($fileExtension) {
        $this->fileExtension = empty($fileExtension) ? null : $fileExtension;
        return $this;
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getAbsoluteFilePath() {
        return $this->fileConfig->getAbsolutePathToFileFolder($this->record) . $this->getFileNameWithExtension();
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getAbsolutePathToModifiedImagesFolder() {
        return $this->fileConfig->getAbsolutePathToFileFolder($this->record) . $this->getFileName();
    }

    /**
     * @param int $position
     * @return $this
     */
    public function setPosition($position) {
        $this->position = (int)$position;
        return $this;
    }

    /**
     * @return int
     */
    public function getPosition() {
        if ($this->position === null) {
            $this->position = time() + static::$autoPositioningCounter;
            static::$autoPositioningCounter++;
        }
        return $this->position;
    }

    /**
     * @param string|array $customInfo
     * @return $this
     */
    public function setCustomInfo($customInfo) {
        if (!is_array($customInfo)) {
            $customInfo = json_decode($customInfo, true);
            if (!is_array($customInfo)) {
                $customInfo = [];
            }
        }
        $this->customInfo = $customInfo;
        return $this;
    }

    /**
     * @return array
     */
    public function getCustomInfo() {
        return $this->customInfo;
    }

    /**
     * @return bool
     * @throws \UnexpectedValueException
     */
    public function exists() {
        return File::exist($this->getAbsoluteFilePath());
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getRelativeUrl() {
        return $this->fileConfig->getRelativeUrlToFileFolder($this->record) . $this->getFileNameWithExtension();
    }

    /**
     * @return string
     * @throws \UnexpectedValueException
     */
    public function getAbsoluteUrl() {
        return url($this->getRelativeUrl());
    }

    /**
     * @param ImageModificationConfig $modificationConfig
     * @return FileInfo;
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
     */
    public function getModifiedImage(ImageModificationConfig $modificationConfig) {
        if (!$this->fileConfig instanceof ImageConfig) {
            throw new \BadMethodCallException('Cannot modify files except images');
        }
        return FileInfo::fromSplFileInfo(
            $modificationConfig->applyModificationTo(
                $this->getAbsoluteFilePath(),
                $this->getAbsolutePathToModifiedImagesFolder()
            ),
            $this->fileConfig,
            $this->record,
            null
        );
    }

    /**
     * @return \SplFileInfo
     */
    public function getSplFileInfo() {
        return new \SplFileInfo($this->getAbsoluteFilePath());
    }

    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function collectImageInfoForDb() {
        return [
            'config_name' => $this->fileConfig->getName(),
            'original_name' => $this->getOriginalFileName(), //< original file name without extension
            'name' => $this->getFileName(), //< file name with suffix but without extension
            'extension' => $this->getFileExtension(),
            'suffix' => $this->getFileSuffix(),
            'info' => $this->getCustomInfo(),
            'uuid' => $this->getUuidForDb(),
            'position' => $this->getPosition()
        ];
    }

}