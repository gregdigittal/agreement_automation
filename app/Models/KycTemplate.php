<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycTemplate extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'name', 'entity_id', 'jurisdiction_id',
        'contract_type_pattern', 'version', 'status',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function jurisdiction(): BelongsTo { return $this->belongsTo(Jurisdiction::class); }
    public function items(): HasMany { return $this->hasMany(KycTemplateItem::class)->orderBy('sort_order'); }
    public function packs(): HasMany { return $this->hasMany(KycPack::class); }
}
