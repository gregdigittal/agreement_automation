<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractKeyDate extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = ['contract_id', 'date_type', 'date_value', 'description', 'reminder_days', 'is_verified', 'verified_by', 'verified_at'];
    protected $casts = ['date_value' => 'date', 'reminder_days' => 'array', 'is_verified' => 'boolean', 'verified_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function reminders(): HasMany { return $this->hasMany(Reminder::class, 'key_date_id'); }
}
