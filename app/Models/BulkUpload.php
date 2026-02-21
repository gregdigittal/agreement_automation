<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkUpload extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'bulk_uploads';

    protected $fillable = [
        'id', 'created_by', 'csv_filename', 'zip_filename',
        'total_rows', 'completed_rows', 'failed_rows', 'status',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'completed_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(BulkUploadRow::class, 'bulk_upload_id');
    }
}
