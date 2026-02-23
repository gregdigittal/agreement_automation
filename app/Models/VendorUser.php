<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class VendorUser extends Authenticatable implements FilamentUser
{
    use HasUuidPrimaryKey, Notifiable;

    protected $table = 'vendor_users';

    protected $guard = 'vendor';

    protected $fillable = [
        'id', 'email', 'name', 'counterparty_id', 'login_token',
        'login_token_expires_at', 'last_login_at',
    ];

    protected $casts = [
        'login_token_expires_at' => 'datetime',
        'last_login_at'          => 'datetime',
    ];

    protected $hidden = ['login_token'];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'vendor';
    }

    public function counterparty(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }
}
