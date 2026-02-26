<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileStorage extends Model
{
    protected $table = 'file_storage';

    protected $fillable = [
        'path', 'contents', 'mime_type', 'size', 'visibility', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    protected $hidden = ['contents'];
}
