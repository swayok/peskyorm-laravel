<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Base64UploadedFile extends UploadedFile {

    protected static $extToMime = [
        'txt' => FilesGroupConfig::TXT,
        'pdf' => FilesGroupConfig::PDF,
        'rtf' => FilesGroupConfig::RTF,
        'doc' => FilesGroupConfig::DOC,
        'docx' => FilesGroupConfig::DOCX,
        'xls' => FilesGroupConfig::XLS,
        'xlsx' => FilesGroupConfig::XLSX,
        'png' => FilesGroupConfig::PNG,
        'jpg' => FilesGroupConfig::JPEG,
        'gif' => FilesGroupConfig::GIF,
        'svg' => FilesGroupConfig::SVG,
        'mp4' => FilesGroupConfig::MP4_VIDEO,
        'mp3' => FilesGroupConfig::MP4_AUDIO,
        'csv' => FilesGroupConfig::CSV,
    ];

    protected $tempFilePath;

    /**
     * @param string $fileData - file data encoded as base64 string
     * @param string $fileName - file name with extension
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function __construct($fileData, $fileName) {
        $this->tempFilePath = tempnam(sys_get_temp_dir(), 'tmp');
        $handle = fopen($this->tempFilePath, 'wb');
        fwrite($handle, base64_decode(preg_replace('%^.{0,200}base64,%i', '', $fileData)));
        fclose($handle);
        if (preg_match('%^data:(.{1,100}/.{1,100});%i', $fileData, $mimeMatches)) {
            $mime = $mimeMatches[1];
        } else {
            $mime = array_get(
                static::$extToMime,
                strtolower(preg_replace('%^.*\.([a-zA-z0-9]+?)$%', '$1', $fileName)),
                mime_content_type($this->tempFilePath)
            );
        }
        // update file extension according to detected mime type
        $ext = array_get(array_flip(static::$extToMime), $mime);
        if ($ext) {
            $fileName = preg_replace('%^.*\.([a-zA-z0-9]+?)$%', '$1', $fileName) . '.' . $ext;
        }
        parent::__construct($this->tempFilePath, $fileName, $mime, filesize($this->tempFilePath));
    }

    public function isValid() {
        return true;
    }

    public function move($directory, $name = null) {
        return File::move($directory, $name);
    }

    public function __destruct() {
        if ($this->tempFilePath && file_exists($this->tempFilePath)) {
            \Swayok\Utils\File::remove($this->tempFilePath);
        }
    }


}