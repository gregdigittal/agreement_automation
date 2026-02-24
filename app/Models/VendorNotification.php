<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class VendorNotification extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'vendor_notifications';
    protected $fillable = ['vendor_user_id', 'subject', 'body', 'related_resource_type', 'related_resource_id', 'read_at'];
    protected $casts = ['read_at' => 'datetime'];

    public function vendorUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\VendorUser::class);
    }

    public function isRead(): bool { return $this->read_at !== null; }
    public function markRead(): void { $this->update(['read_at' => now()]); }
}
