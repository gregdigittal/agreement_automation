<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantAgreement extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['counterparty_id', 'region_id', 'entity_id', 'project_id', 'merchant_fee', 'region_terms'];
    protected $casts = ['merchant_fee' => 'decimal:2'];

    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
