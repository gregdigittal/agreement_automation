<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'audit_log';
    public $timestamps = false;

    protected $fillable = ['at', 'actor_id', 'actor_email', 'action', 'resource_type', 'resource_id', 'details', 'ip_address'];
    protected $casts = ['details' => 'array', 'at' => 'datetime'];

    protected static function boot(): void
    {
        parent::boot();
        static::updating(fn () => throw new \RuntimeException('Audit log records are immutable'));
        static::deleting(fn () => throw new \RuntimeException('Audit log records cannot be deleted'));
    }
}
