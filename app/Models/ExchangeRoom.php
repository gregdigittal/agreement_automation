<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRoom extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'contract_id',
        'status',
        'negotiation_stage',
        'created_by',
    ];

    protected $casts = [
        'status' => 'string',
        'negotiation_stage' => 'string',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ExchangeRoomPost::class, 'room_id');
    }

    public function latestVersion(): ?int
    {
        return $this->posts()->whereNotNull('version_number')->max('version_number');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
