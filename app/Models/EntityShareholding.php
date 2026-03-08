<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityShareholding extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'owner_entity_id',
        'owned_entity_id',
        'percentage',
        'ownership_type',
        'effective_date',
        'notes',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'effective_date' => 'date',
    ];

    /** The entity that holds the shares (the parent/owner). */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'owner_entity_id');
    }

    /** The entity whose shares are held (the subsidiary/owned). */
    public function owned(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'owned_entity_id');
    }
}
