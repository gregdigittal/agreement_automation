<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Foundation\Auth\User as Authenticatable;

class VendorUser extends Authenticatable
{
    use HasUuidPrimaryKey;

    protected $guard = 'vendor';

    protected $fillable = [
        'counterparty_id', 'email', 'name',
        'login_token', 'login_token_expires_at', 'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'login_token_expires_at' => 'datetime',
    ];

    protected $hidden = ['login_token'];

    public function counterparty()
    {
        return $this->belongsTo(Counterparty::class);
    }
}
