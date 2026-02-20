<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class AuditService
{
    public static function log(
        string $action,
        string $resourceType,
        ?string $resourceId,
        array $details = [],
        ?User $actor = null
    ): void {
        $actor = $actor ?? auth()->user();
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'at' => now(),
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => $details ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
