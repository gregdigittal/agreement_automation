<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalationRule extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['workflow_template_id', 'stage_name', 'sla_breach_hours', 'tier', 'escalate_to_role', 'escalate_to_user_id'];

    public function template(): BelongsTo { return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id'); }
    public function events(): HasMany { return $this->hasMany(EscalationEvent::class, 'rule_id'); }
}
