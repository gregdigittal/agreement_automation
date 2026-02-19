<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLanguage extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['contract_id', 'language_code', 'is_primary', 'storage_path', 'file_name', 'created_at'];
    protected $casts = ['is_primary' => 'boolean', 'created_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
