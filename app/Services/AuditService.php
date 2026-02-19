<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    public static function log(string $action, string $resourceType, ?string $resourceId = null, ?array $details = null): AuditLog
    {
        $user = auth()->user();
        return AuditLog::create([
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => $details,
            'actor_id' => $user?->id,
            'actor_email' => $user?->email,
            'ip_address' => request()?->ip(),
            'at' => now(),
        ]);
    }
}
