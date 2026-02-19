<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Foundation\Auth\User as Authenticatable;

class VendorUser extends Authenticatable
{
    use HasUuidPrimaryKey;

    protected $guard = 'vendor';
    protected $fillable = ['counterparty_id', 'email', 'name'];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function counterparty()
    {
        return $this->belongsTo(Counterparty::class);
    }
}
