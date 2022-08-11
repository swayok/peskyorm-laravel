<?php

namespace PeskyORMLaravel\Db\Column\Utils;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Base64UploadedFile extends UploadedFile {

    protected static $extToMime = [
        'txt' => MimeTypesHelper::TXT,
        'pdf' => MimeTypesHelper::PDF,
        'rtf' => MimeTypesHelper::RTF,
        'doc' => MimeTypesHelper::DOC,
        'docx' => MimeTypesHelper::DOCX,
        'xls' => MimeTypesHelper::XLS,
        'xlsx' => MimeTypesHelper::XLSX,
        'png' => MimeTypesHelper::PNG,
        'jpg' => MimeTypesHelper::JPEG,
        'gif' => MimeTypesHelper::GIF,
        'svg' => MimeTypesHelper::SVG,
        'mp4' => MimeTypesHelper::MP4_VIDEO,
        'mp3' => MimeTypesHelper::MP4_AUDIO,
        'csv' => MimeTypesHelper::CSV,
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