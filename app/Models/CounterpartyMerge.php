<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class CounterpartyMerge extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $fillable = ['source_counterparty_id', 'target_counterparty_id', 'merged_by', 'merged_by_email', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function sourceCounterparty(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'source_counterparty_id');
    }

    public function targetCounterparty(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'target_counterparty_id');
    }
}
