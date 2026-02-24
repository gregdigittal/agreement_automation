<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycTemplateItem extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'kyc_template_id', 'sort_order', 'label', 'description',
        'field_type', 'is_required', 'options', 'validation_rules',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'options' => 'array',
        'validation_rules' => 'array',
    ];

    public function template(): BelongsTo { return $this->belongsTo(KycTemplate::class, 'kyc_template_id'); }
}
