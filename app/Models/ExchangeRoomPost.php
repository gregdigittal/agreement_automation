<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExchangeRoomPost extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'room_id',
        'author_type',
        'author_id',
        'author_name',
        'actor_side',
        'message',
        'storage_path',
        'file_name',
        'mime_type',
        'version_number',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'actor_side' => 'string',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExchangeRoom::class, 'room_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasFile(): bool
    {
        return $this->storage_path !== null;
    }
}
