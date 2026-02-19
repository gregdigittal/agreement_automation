<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStageAction extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['instance_id', 'stage_name', 'action', 'actor_id', 'actor_email', 'comment', 'artifacts', 'created_at'];
    protected $casts = ['artifacts' => 'array', 'created_at' => 'datetime'];

    public function instance(): BelongsTo { return $this->belongsTo(WorkflowInstance::class, 'instance_id'); }
}
