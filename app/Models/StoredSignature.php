<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class StoredSignature extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_id', 'counterparty_id', 'signer_email',
        'label', 'type', 'capture_method', 'image_path', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * Get a temporary URL for the signature image.
     */
    public function getImageUrl(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $disk = config('ccrs.contracts_disk', 'database');

        if (method_exists(Storage::disk($disk), 'temporaryUrl')) {
            try {
                return Storage::disk($disk)->temporaryUrl($this->image_path, now()->addMinutes(30));
            } catch (\Throwable) {
                // Fall back to regular URL
            }
        }

        return Storage::disk($disk)->url($this->image_path);
    }

    /**
     * Scope to find stored signatures for a signer by user ID or email.
     */
    public function scopeForSigner(Builder $query, ?string $userId = null, ?string $email = null): Builder
    {
        if (!$userId && !$email) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($userId, $email) {
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
            if ($email) {
                $q->orWhere('signer_email', $email);
            }
        });
    }
}
