<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['recipient_email', 'recipient_user_id', 'channel', 'subject', 'body', 'related_resource_type', 'related_resource_id', 'status', 'sent_at', 'read_at', 'error_message', 'created_at'];
    protected $casts = ['sent_at' => 'datetime', 'read_at' => 'datetime', 'created_at' => 'datetime'];
}
