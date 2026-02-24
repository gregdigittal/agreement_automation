<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycPackItem extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'kyc_pack_id', 'kyc_template_item_id', 'sort_order',
        'label', 'description', 'field_type', 'is_required',
        'options', 'validation_rules', 'value', 'file_path',
        'attested_by', 'attested_at', 'status',
        'completed_at', 'completed_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'options' => 'array',
        'validation_rules' => 'array',
        'attested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function pack(): BelongsTo { return $this->belongsTo(KycPack::class, 'kyc_pack_id'); }
    public function templateItem(): BelongsTo { return $this->belongsTo(KycTemplateItem::class, 'kyc_template_item_id'); }
    public function attestedByUser(): BelongsTo { return $this->belongsTo(User::class, 'attested_by'); }
    public function completedByUser(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
}
