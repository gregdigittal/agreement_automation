<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['name', 'code'];

    public function entities(): HasMany { return $this->hasMany(Entity::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function wikiContracts(): HasMany { return $this->hasMany(WikiContract::class); }
    public function workflowTemplates(): HasMany { return $this->hasMany(WorkflowTemplate::class); }
}
