<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkUploadRow extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'bulk_upload_rows';

    protected $fillable = [
        'id', 'bulk_upload_id', 'row_number', 'row_data',
        'status', 'contract_id', 'created_by', 'error',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];

    public function bulkUpload(): BelongsTo
    {
        return $this->belongsTo(BulkUpload::class);
    }
}
