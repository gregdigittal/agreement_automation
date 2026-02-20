<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['region_id', 'name', 'code'];

    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function projects(): HasMany { return $this->hasMany(Project::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function signingAuthorities(): HasMany { return $this->hasMany(SigningAuthority::class); }
    public function workflowTemplates(): HasMany { return $this->hasMany(WorkflowTemplate::class); }
}
