<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Controller;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PeskyORMLaravel\Db\LaravelUploadedTempFileInfo;

abstract class TempFilesUploadsController extends Controller
{
    
    use ValidatesRequests;
    
    protected $allowedFileMimes = [
        'pdf',
        'doc',
        'docx',
        'xlsx',
        'csv',
        'jpeg',
        'png',
    ];
    
    protected $allowedFileExtensions = [
        'pdf',
        'doc',
        'docx',
        'xlsx',
        'csv',
        'jpeg',
        'jpg',
        'png',
    ];
    
    protected $maxFileSizeKb = 8192;
    
    protected $uploadedTempFileInfoClass = LaravelUploadedTempFileInfo::class;
    
    public function upload(Request $request): JsonResponse
    {
        $tempFile = $this->uploadFile($request);
        return response()->json([
            'file_info' => $tempFile->encode(),
            'size' => $tempFile->getSize(),
        ]);
    }
    
    public function delete(Request $request): JsonResponse
    {
        $this->deleteFile($request);
        return response()->json(['success' => true]);
    }
    
    protected function uploadFile(Request $request): LaravelUploadedTempFileInfo
    {
        $this->validate($request, [
            'file' => $this->getUploadedFileValidationRule(),
        ]);
        // upload file
        return new ($this->uploadedTempFileInfoClass)($request->file('file'), true);
    }
    
    protected function deleteFile(Request $request): void
    {
        $this->validate($request->input(), [
            'info' => 'required|string',
        ]);
        $tempFile = new ($this->uploadedTempFileInfoClass)($request->input('info'));
        if (!$tempFile->isValid()) {
            abort($this->getDeleteFailedResponse());
        }
        $tempFile->delete();
    }
    
    protected function getDeleteFailedResponse(): Response
    {
        return \response('delete_failed', 422);
    }
    
    protected function getUploadedFileValidationRule(): array
    {
        return [
            'required',
            'file',
            'max:' . $this->maxFileSizeKb,
            'mimes:' . implode(',', $this->allowedFileMimes),
            'regex:%\.' . implode(',', $this->allowedFileExtensions) . '$%',
        ];
    }
    
}