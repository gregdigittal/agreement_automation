<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['contract_id', 'key_date_id', 'reminder_type', 'lead_days', 'channel', 'recipient_email', 'recipient_user_id', 'last_sent_at', 'next_due_at', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'last_sent_at' => 'datetime', 'next_due_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function keyDate(): BelongsTo { return $this->belongsTo(ContractKeyDate::class, 'key_date_id'); }
}
