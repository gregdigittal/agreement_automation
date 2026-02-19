<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAnalysisResult extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['contract_id', 'analysis_type', 'status', 'result', 'evidence', 'confidence_score', 'model_used', 'token_usage_input', 'token_usage_output', 'cost_usd', 'processing_time_ms', 'agent_budget_usd', 'error_message'];
    protected $casts = ['result' => 'array', 'evidence' => 'array'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function extractedFields(): HasMany { return $this->hasMany(AiExtractedField::class, 'analysis_id'); }
}
