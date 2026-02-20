<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ContractFileService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function upload(UploadedFile $file, string $contractId, string $disk = 's3'): array
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('File must be PDF or DOCX');
        }
        $path = Storage::disk($disk)->putFileAs(
            "contracts/{$contractId}",
            $file,
            $file->getClientOriginalName()
        );
        return [
            'storage_path' => $path,
            'file_name' => $file->getClientOriginalName(),
        ];
    }

    public function getSignedUrl(string $storagePath, int $minutes = 60): string
    {
        return Storage::disk(config('ccrs.contracts_disk', 's3'))->temporaryUrl(
            $storagePath,
            now()->addMinutes($minutes)
        );
    }

    public function download(string $storagePath): string
    {
        return Storage::disk(config('ccrs.contracts_disk', 's3'))->get($storagePath);
    }
}
