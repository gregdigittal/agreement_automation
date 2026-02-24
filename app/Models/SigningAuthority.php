<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigningAuthority extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'signing_authority';
    protected $fillable = ['entity_id', 'project_id', 'user_id', 'user_email', 'role_or_name', 'contract_type_pattern'];

    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
