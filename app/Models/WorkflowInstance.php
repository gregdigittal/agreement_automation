<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['contract_id', 'template_id', 'template_version', 'current_stage', 'state', 'started_at', 'completed_at'];
    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function template(): BelongsTo { return $this->belongsTo(WorkflowTemplate::class, 'template_id'); }
    public function stageActions(): HasMany { return $this->hasMany(WorkflowStageAction::class, 'instance_id'); }
    public function escalationEvents(): HasMany { return $this->hasMany(EscalationEvent::class); }
}
