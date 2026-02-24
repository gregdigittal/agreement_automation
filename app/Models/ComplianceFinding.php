<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceFinding extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'contract_id',
        'framework_id',
        'requirement_id',
        'requirement_text',
        'status',
        'evidence_clause',
        'evidence_page',
        'ai_rationale',
        'confidence',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence' => 'double',
        'evidence_page' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(RegulatoryFramework::class, 'framework_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
