<?php

namespace PeskyORMLaravel\Db\Column\Utils;

abstract class MimeTypesHelper {

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
    const SVG = 'image/svg+xml';
    const ZIP = 'application/zip';
    const RAR = 'application/x-rar-compressed';
    const GZIP = 'application/gzip';
    const ANDROID_APK = 'application/vnd.android.package-archive';
    const MP4_VIDEO = 'video/mp4';
    const MP4_AUDIO = 'audio/mp4';
    const UNKNOWN = 'application/octet-stream';

    /**
     * @var array
     */
    static protected $mimeToExt = [
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
        self::ANDROID_APK => 'apk',
    ];

    /**
     * List of aliases for file types.
     * Format: 'common/filetype' => ['alias/filetype1', 'alias/filetype2']
     * For example: image/jpeg file type has alias image/x-jpeg
     * @var array
     */
    static protected $mimeTypesAliases = [
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
        ],
        self::SVG => [
            'image/svg',
            'application/svg+xml'
        ]
    ];

    const TYPE_FILE = 'file';
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_TEXT = 'text';
    const TYPE_ARCHIVE = 'archive';
    const TYPE_OFFICE = 'office';

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

    public static function getMimeTypesAliases(): array {
        return static::$mimeTypesAliases;
    }

    public static function getMimeTypesToFileTypes(): array {
        return static::$mimeTypeToFileType;
    }

    public static function getMimeTypesToFileExtensions(): array {
        return static::$mimeToExt;
    }

    public static function getExtensionForMimeType(string $mimeType): ?string {
        return isset(static::$mimeToExt[$mimeType]) ? static::$mimeToExt[$mimeType] : null;
    }
    
    public static function getMimeTypeForExtension(string $extension): ?string {
        return array_get(array_flip(static::$mimeToExt), $extension, null);
    }

    public static function detectFileTypeByMimeType(?string $mimeType): string {
        if (empty($mimeType) || !is_string($mimeType)) {
            return static::UNKNOWN;
        }
        $mimeType = mb_strtolower($mimeType);
        if (array_key_exists($mimeType, static::$mimeTypeToFileType)) {
            return static::$mimeTypeToFileType[$mimeType];
        }
        foreach (static::$mimeTypesAliases as $mime => $aliases) {
            if (in_array($mimeType, $aliases, true)) {
                return static::$mimeTypeToFileType[$mime];
            }
        }
        return static::UNKNOWN;
    }

    public static function getAliasesForMimeTypes(array $mimeTypes): array {
        $aliases = [];
        foreach ($mimeTypes as $fileType) {
            if (!empty(static::$mimeTypesAliases[$fileType])) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $aliases = array_merge($aliases, (array)static::$mimeTypesAliases[$fileType]);
            }
        }
        return $aliases;
    }
    
}