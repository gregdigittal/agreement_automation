<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycPack extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'contract_id', 'kyc_template_id', 'template_version',
        'status', 'completed_at',
    ];

    protected $casts = [
        'template_version' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function template(): BelongsTo { return $this->belongsTo(KycTemplate::class, 'kyc_template_id'); }
    public function items(): HasMany { return $this->hasMany(KycPackItem::class)->orderBy('sort_order'); }

    public function isComplete(): bool
    {
        return $this->items()
            ->where('is_required', true)
            ->whereNotIn('status', ['completed', 'not_applicable'])
            ->doesntExist();
    }
}
