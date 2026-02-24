<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryFramework extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'jurisdiction_code',
        'framework_name',
        'description',
        'requirements',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_active' => 'boolean',
    ];

    public function findings(): HasMany
    {
        return $this->hasMany(ComplianceFinding::class, 'framework_id');
    }

    public function getRequirementCountAttribute(): int
    {
        return is_array($this->requirements) ? count($this->requirements) : 0;
    }
}
