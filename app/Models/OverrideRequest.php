<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverrideRequest extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['counterparty_id', 'contract_title', 'requested_by_email', 'reason', 'status', 'decided_by', 'decided_at', 'comment'];
    protected $casts = ['decided_at' => 'datetime'];

    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
}
