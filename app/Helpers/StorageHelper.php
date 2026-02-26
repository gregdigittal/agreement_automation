<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StorageHelper
{
    /**
     * Generate a pre-signed S3 URL with a consistent expiry window.
     * Uses the disk from config (ccrs.contracts_disk). Adjusts expiry based on sensitivity:
     *   - 'download' → 10 minutes (user-triggered file download)
     *   - 'preview'  → 2 minutes  (inline browser preview)
     *   - 'api'      → 30 seconds (API response to frontend)
     */
    public static function temporaryUrl(string $path, string $context = 'download'): string
    {
        $minutes = match ($context) {
            'preview' => 2,
            'api'     => 0.5,
            default   => 10,
        };

        $disk = config('ccrs.contracts_disk', 'database');
        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
    }
}
