<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDiscoveryDraft extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'contract_id', 'analysis_id', 'draft_type', 'extracted_data',
        'matched_record_id', 'matched_record_type', 'confidence',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
