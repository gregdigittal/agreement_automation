<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateSigningField extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'wiki_contract_id', 'field_type', 'signer_role', 'label',
        'page_number', 'x_position', 'y_position', 'width', 'height',
        'is_required', 'sort_order',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'x_position' => 'decimal:2',
        'y_position' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function wikiContract(): BelongsTo
    {
        return $this->belongsTo(WikiContract::class);
    }
}
