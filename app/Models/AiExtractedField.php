<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiExtractedField extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['contract_id', 'analysis_id', 'field_name', 'field_value', 'evidence_clause', 'evidence_page', 'confidence', 'is_verified', 'verified_by', 'verified_at'];
    protected $casts = ['is_verified' => 'boolean', 'verified_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function analysis(): BelongsTo { return $this->belongsTo(AiAnalysisResult::class, 'analysis_id'); }
}
