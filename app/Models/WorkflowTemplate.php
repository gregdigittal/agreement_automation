<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['name', 'contract_type', 'region_id', 'entity_id', 'project_id', 'version', 'status', 'stages', 'validation_errors', 'created_by', 'published_at'];
    protected $casts = ['stages' => 'array', 'validation_errors' => 'array', 'published_at' => 'datetime'];

    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function instances(): HasMany { return $this->hasMany(WorkflowInstance::class, 'template_id'); }
    public function escalationRules(): HasMany { return $this->hasMany(EscalationRule::class); }
}
