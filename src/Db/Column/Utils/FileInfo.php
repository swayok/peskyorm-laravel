<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use PeskyCMF\Scaffold\Form\UploadedTempFileInfo;
use PeskyORM\ORM\RecordInterface;
use Ramsey\Uuid\Uuid;
use Swayok\Utils\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileInfo {

    /** @var FileConfig|ImageConfig|FilesGroupConfig|ImagesGroupConfig */
    protected $fileConfig;
    /** @var RecordInterface */
    protected $record;
    /** @var string */
    protected $fileName;
    /** @var string */
    protected $originalFileName;
    /** @var null|string */
    protected $fileSuffix;
    /** @var string */
    protected $fileExtension;
    /** @var string */
    protected $uuid;
    /** @var string */
    protected $uuidForDb;
    /** @var array */
    protected $customInfo = [];
    /** @var null|int */
    protected $position;
    /** @var null|string */
    protected $mime;
    /** @var null|string */
    protected $type;
    /** @var null|string */
    protected $uploadedFilePath;
    /** @var int */
    private static $autoPositioningCounter = 1;

    /**
     * @param array $fileInfo
     * @param FileConfig|ImageConfig|FilesGroupConfig|ImagesGroupConfig|FileConfigInterface $fileConfig
     * @param RecordInterface $record
     * @return static
     */
    public static function fromArray(array $fileInfo, FileConfigInterface $fileConfig, RecordInterface $record) {
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
        if (array_has($fileInfo, 'mime')) {
            $obj->setMimeType($fileInfo['mime']);
        }
        if (array_has($fileInfo, 'type')) {
            $obj->setFileType($fileInfo['type']);
        }
        return $obj;
    }

    /**
     * @param \SplFileInfo $fileInfo
     * @param FileConfig|ImageConfig|FilesGroupConfig|ImagesGroupConfig|FileConfigInterface $fileConfig
     * @param RecordInterface $record
     * @param null|string $fileSuffix
     * @return static
     */
    public static function fromSplFileInfo(
        \SplFileInfo $fileInfo,
        FileConfigInterface $fileConfig,
        RecordInterface $record,
        ?string $fileSuffix = null
    ) {
        $obj = new static($fileConfig, $record, $fileSuffix);
        if (!($fileInfo instanceof UploadedFile)) {
            $fileInfo = new UploadedFile($fileInfo->getRealPath(), $fileInfo->getFilename(), null, null, true);
        }

        $extension = $fileInfo->getClientOriginalExtension();
        $obj
            ->setUploadedFilePath($fileInfo->getRealPath())
            ->setFileExtension($extension)
            ->setOriginalFileName(preg_replace("%\.{$extension}$%", '', $fileInfo->getClientOriginalName()))
            ->setMimeType(static::detectMimeType($fileInfo))
            ->setUuid($obj->makeUuid());
        return $obj;
    }

    /**
     * @param UploadedTempFileInfo $tempFileInfo
     * @param FileConfig|ImageConfig|FilesGroupConfig|ImagesGroupConfig|FileConfigInterface $fileConfig
     * @param RecordInterface $record
     * @param null|string $fileSuffix
     * @return static
     */
    public static function fromUploadedTempFileInfo(
        UploadedTempFileInfo $tempFileInfo,
        FileConfigInterface $fileConfig,
        RecordInterface $record,
        ?string $fileSuffix = null
    ) {
        $obj = new static($fileConfig, $record, $fileSuffix);
        $obj
            ->setUploadedFilePath($tempFileInfo->getRealPath())
            ->setFileExtension(MimeTypesHelper::getExtensionForMimeType($tempFileInfo->getType()))
            ->setOriginalFileName(preg_replace('%\..*?$%', '', $tempFileInfo->getName()))
            ->setMimeType($tempFileInfo->getType())
            ->setUuid($obj->makeUuid());
        return $obj;
    }

    /**
     * @param FileConfig|ImageConfig|FilesGroupConfig|ImagesGroupConfig|FileConfigInterface $fileConfig
     * @param RecordInterface $record
     * @param null|string $fileSuffix
     */
    protected function __construct(FileConfigInterface $fileConfig, RecordInterface $record, ?string $fileSuffix = null) {
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
        if (empty($this->uuidForDb)) {
            $this->uuidForDb = $this->isTempUuid() ? $this->makeUuid() : $this->getUuid();
        }
        return $this->uuidForDb;
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
     * @return string|null
     */
    public function getUploadedFilePath(): ?string {
        return $this->uploadedFilePath;
    }

    /**
     * @param string|null $uploadedFilePath
     * @return $this
     */
    public function setUploadedFilePath(?string $uploadedFilePath) {
        $this->uploadedFilePath = $uploadedFilePath;
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
     */
    public function getFileName() {
        if (!$this->fileName) {
            $this->fileName = $this->fileConfig->makeNewFileName($this->getFileSuffix());
        }
        return $this->fileName;
    }

    /**
     * @return string
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
     */
    public function getOriginalFileName() {
        if (!$this->originalFileName) {
            $this->originalFileName = $this->getFileName();
        }
        return $this->originalFileName;
    }

    /**
     * @return string
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
     */
    public function getAbsoluteFilePath() {
        return $this->fileConfig->getAbsolutePathToFileFolder($this->record) . $this->getFileNameWithExtension();
    }

    /**
     * @return string
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
     * @return string
     */
    public function getMimeType(): string {
        if (!$this->mime) {
            $this->mime = static::detectMimeType($this->getAbsoluteFilePath()) ?: 'application/octet-stream';
        }
        return $this->mime;
    }

    /**
     * @param null|string $mime
     * @return $this
     */
    protected function setMimeType($mime) {
        $this->mime = $mime;
        return $this;
    }

    /**
     * @param string|\SplFileInfo|UploadedFile $file
     * @return null|string
     */
    public static function detectMimeType($file) {
        if ($file instanceof UploadedFile) {
            return $file->getMimeType() ?: $file->getClientMimeType();
        } else if ($file instanceof \SplFileInfo) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $file = $file->getRealPath();
        }
        $file = new UploadedFile($file, 'temp.file', null, null, true);
        return $file->getMimeType();
    }

    /**
     * @return string
     */
    public function getFileType() {
        if (!$this->type) {
            /** @noinspection StaticInvocationViaThisInspection */
            $this->type = $this->fileConfig->detectFileTypeByMimeType($this->getMimeType());
        }
        return $this->type;
    }

    /**
     * @param $type
     * @return $this
     */
    protected function setFileType($type) {
        $this->type = $type;
        return $this;
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
     */
    public function exists() {
        return File::exist($this->getAbsoluteFilePath());
    }

    /**
     * @return string
     */
    public function getRelativeUrl() {
        return $this->fileConfig->getRelativeUrlToFileFolder($this->record) . $this->getFileNameWithExtension();
    }

    /**
     * @return string
     */
    public function getAbsoluteUrl() {
        return url($this->getRelativeUrl());
    }

    /**
     * @return int
     */
    public function getSize() {
        return $this->exists() ? filesize($this->getAbsoluteFilePath()) : 0;
    }

    /**
     * @param ImageModificationConfig $modificationConfig
     * @return ModifiedImageInfo;
     * @throws \BadMethodCallException
     */
    public function getModifiedImage(ImageModificationConfig $modificationConfig) {
        if (!($this->fileConfig instanceof ImageConfig)) {
            throw new \BadMethodCallException('Cannot modify files except images');
        }
        return ModifiedImageInfo::fromSplFileInfo(
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
     */
    public function collectFileInfoForDb() {
        return [
            'config_name' => $this->fileConfig->getName(),
            'original_name' => $this->getOriginalFileName(), //< original file name without extension
            'name' => $this->getFileName(), //< file name with suffix but without extension
            'extension' => $this->getFileExtension(),
            'suffix' => $this->getFileSuffix(),
            'info' => $this->getCustomInfo(),
            'uuid' => $this->getUuidForDb(),
            'position' => $this->getPosition(),
            'mime' => $this->getMimeType(),
            'type' => $this->getFileType()
        ];
    }

}
