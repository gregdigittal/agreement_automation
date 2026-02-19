<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WikiContract extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['name', 'category', 'region_id', 'version', 'status', 'storage_path', 'file_name', 'description', 'created_by', 'published_at'];
    protected $casts = ['published_at' => 'datetime'];

    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function merchantAgreementInputs(): HasMany { return $this->hasMany(MerchantAgreementInput::class, 'template_id'); }
}
