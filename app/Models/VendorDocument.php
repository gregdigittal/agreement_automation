<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocument extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['counterparty_id', 'contract_id', 'title', 'storage_path', 'file_name', 'uploaded_by'];

    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
