<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationsRegister extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'obligations_register';
    protected $fillable = ['contract_id', 'analysis_id', 'obligation_type', 'description', 'due_date', 'recurrence', 'responsible_party', 'status', 'evidence_clause', 'confidence'];
    protected $casts = ['due_date' => 'date'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function analysis(): BelongsTo { return $this->belongsTo(AiAnalysisResult::class, 'analysis_id'); }
}
