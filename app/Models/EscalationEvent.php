<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalationEvent extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['workflow_instance_id', 'rule_id', 'contract_id', 'stage_name', 'tier', 'escalated_at', 'resolved_at', 'resolved_by', 'created_at'];
    protected $casts = ['escalated_at' => 'datetime', 'resolved_at' => 'datetime', 'created_at' => 'datetime'];

    public function workflowInstance(): BelongsTo { return $this->belongsTo(WorkflowInstance::class); }
    public function rule(): BelongsTo { return $this->belongsTo(EscalationRule::class, 'rule_id'); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
