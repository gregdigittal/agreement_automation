<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocument extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'vendor_documents';

    protected $fillable = [
        'id', 'counterparty_id', 'title', 'contract_id', 'filename', 'storage_path',
        'document_type', 'uploaded_by_vendor_user_id',
    ];

    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(VendorUser::class, 'uploaded_by_vendor_user_id'); }
}
