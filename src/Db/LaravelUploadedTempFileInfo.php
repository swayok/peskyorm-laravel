<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db;

use Illuminate\Support\Facades\Crypt;
use PeskyORMColumns\Column\Files\Utils\UploadedTempFileInfo;

class LaravelUploadedTempFileInfo extends UploadedTempFileInfo
{
    
    public static function getUploadsTempFolder(): string {
        return storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
    }
    
    protected static function encodeData(array $data): string {
        return Crypt::encrypt($data);
    }
    
    protected static function decodeData(string $encodedData): ?array {
        return Crypt::decrypt($encodedData);
    }
}