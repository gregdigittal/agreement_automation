<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class VendorUser extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use HasUuidPrimaryKey, Notifiable;

    protected $table = 'vendor_users';

    protected $guard = 'vendor';

    protected $fillable = [
        'email', 'name', 'counterparty_id', 'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'vendor';
    }

    public function counterparty(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }
}
