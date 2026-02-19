<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLink extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['parent_contract_id', 'child_contract_id', 'link_type', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function parentContract(): BelongsTo { return $this->belongsTo(Contract::class, 'parent_contract_id'); }
    public function childContract(): BelongsTo { return $this->belongsTo(Contract::class, 'child_contract_id'); }
}
