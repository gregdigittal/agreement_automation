<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SigningAuthority extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'signing_authority';
    protected $fillable = ['entity_id', 'user_id', 'user_email', 'role_or_name', 'contract_type_pattern'];

    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function projects(): BelongsToMany { return $this->belongsToMany(Project::class, 'signing_authority_project'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Whether this authority applies to all projects (no specific project scope).
     */
    public function appliesToAllProjects(): bool
    {
        return $this->projects()->count() === 0;
    }
}
