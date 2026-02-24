<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityJurisdiction extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'entity_id',
        'jurisdiction_id',
        'license_number',
        'license_expiry',
        'is_primary',
    ];

    protected $casts = [
        'license_expiry' => 'date',
        'is_primary' => 'boolean',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(Jurisdiction::class);
    }
}
