<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantAgreement extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'agreement_type',
        'counterparty_id',
        'region_id',
        'entity_id',
        'project_id',
        'wiki_contract_id',
        'governing_law_id',
        'jurisdiction_ids',
        'additional_counterparty_ids',
        'merchant_fee',
        'region_terms',
        'description',
    ];

    protected $casts = [
        'merchant_fee' => 'decimal:2',
        'jurisdiction_ids' => 'array',
        'additional_counterparty_ids' => 'array',
    ];

    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function wikiContract(): BelongsTo { return $this->belongsTo(WikiContract::class); }
    public function governingLaw(): BelongsTo { return $this->belongsTo(GoverningLaw::class); }
}
