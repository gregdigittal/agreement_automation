<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedlineClause extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'session_id',
        'clause_number',
        'clause_heading',
        'original_text',
        'suggested_text',
        'change_type',
        'ai_rationale',
        'confidence',
        'status',
        'final_text',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'clause_number' => 'integer',
        'confidence' => 'double',
        'reviewed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RedlineSession::class, 'session_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasMaterialChange(): bool
    {
        return $this->change_type !== 'unchanged';
    }
}
