<?php

namespace App\Services;

use App\Helpers\StorageHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractFileService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function upload(UploadedFile $file, string $contractId, string $disk = 'database'): array
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('File must be PDF or DOCX');
        }
        $originalName = $file->getClientOriginalName();
        $safeName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = Storage::disk($disk)->putFileAs(
            "contracts/{$contractId}",
            $file,
            $safeName
        );
        return [
            'storage_path' => $path,
            'file_name' => $originalName,
        ];
    }

    public function getSignedUrl(string $storagePath): string
    {
        return StorageHelper::temporaryUrl($storagePath, 'download');
    }

    public function download(string $storagePath): string
    {
        return Storage::disk(config('ccrs.contracts_disk', 'database'))->get($storagePath);
    }
}
