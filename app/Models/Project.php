<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['entity_id', 'name', 'code'];

    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function signingAuthorities(): BelongsToMany { return $this->belongsToMany(SigningAuthority::class, 'signing_authority_project'); }
}
