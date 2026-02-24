<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedlineSession extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'contract_id',
        'wiki_contract_id',
        'status',
        'created_by',
        'total_clauses',
        'reviewed_clauses',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'summary' => 'array',
        'total_clauses' => 'integer',
        'reviewed_clauses' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function wikiContract(): BelongsTo
    {
        return $this->belongsTo(WikiContract::class);
    }

    public function clauses(): HasMany
    {
        return $this->hasMany(RedlineClause::class, 'session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFullyReviewed(): bool
    {
        return $this->total_clauses > 0
            && $this->reviewed_clauses >= $this->total_clauses;
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_clauses === 0) {
            return 0;
        }

        return (int) round(($this->reviewed_clauses / $this->total_clauses) * 100);
    }
}
