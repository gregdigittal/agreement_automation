<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ContractFileService
{
    public function upload(Contract $contract, UploadedFile $file): string
    {
        $disk = config('ccrs.contracts_disk', 's3');
        $path = "contracts/{$contract->id}/{$file->getClientOriginalName()}";

        Storage::disk($disk)->putFileAs(
            "contracts/{$contract->id}",
            $file,
            $file->getClientOriginalName()
        );

        $contract->update([
            'storage_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_version' => ($contract->file_version ?? 0) + 1,
        ]);

        AuditService::log('contract_file_uploaded', 'contract', $contract->id, [
            'file_name' => $file->getClientOriginalName(),
            'version' => $contract->file_version,
        ]);

        return $path;
    }

    public function getSignedUrl(Contract $contract, int $expiry = 3600): ?string
    {
        if (!$contract->storage_path) return null;
        $disk = config('ccrs.contracts_disk', 's3');
        return Storage::disk($disk)->temporaryUrl($contract->storage_path, now()->addSeconds($expiry));
    }
}
