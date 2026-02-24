<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'region_id',
        'name',
        'code',
        'legal_name',
        'registration_number',
        'registered_address',
        'parent_entity_id',
    ];

    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function projects(): HasMany { return $this->hasMany(Project::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function signingAuthorities(): HasMany { return $this->hasMany(SigningAuthority::class); }
    public function workflowTemplates(): HasMany { return $this->hasMany(WorkflowTemplate::class); }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'parent_entity_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Entity::class, 'parent_entity_id');
    }

    public function jurisdictions(): BelongsToMany
    {
        return $this->belongsToMany(Jurisdiction::class, 'entity_jurisdictions')
            ->withPivot(['license_number', 'license_expiry', 'is_primary'])
            ->withTimestamps();
    }
}
