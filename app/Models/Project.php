<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['entity_id', 'name', 'code'];

    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function signingAuthorities(): HasMany { return $this->hasMany(SigningAuthority::class); }
}
