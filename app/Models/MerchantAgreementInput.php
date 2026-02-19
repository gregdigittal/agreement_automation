<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantAgreementInput extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['contract_id', 'template_id', 'vendor_name', 'merchant_fee', 'region_terms', 'generated_at', 'created_at'];
    protected $casts = ['region_terms' => 'array', 'generated_at' => 'datetime', 'created_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function template(): BelongsTo { return $this->belongsTo(WikiContract::class, 'template_id'); }
}
