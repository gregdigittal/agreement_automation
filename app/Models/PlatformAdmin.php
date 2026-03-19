<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Platform-level super-admin user.
 *
 * Lives in the central database only — never in a tenant database.
 * Authenticates via email/password through the 'superadmin' guard.
 * Manages tenants and platform configuration via the superadmin Filament panel.
 */
class PlatformAdmin extends Authenticatable
{
    use HasFactory, HasUuids;

    /** Always query the central (platform) database, never a tenant database. */
    protected $connection = 'central';

    protected $table = 'platform_admins';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
