<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorLoginToken extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['vendor_user_id', 'token_hash', 'expires_at', 'used_at', 'created_at'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'created_at' => 'datetime'];

    public function vendorUser(): BelongsTo { return $this->belongsTo(VendorUser::class); }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
