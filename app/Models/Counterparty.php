<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counterparty extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['legal_name', 'registration_number', 'address', 'jurisdiction', 'status', 'status_reason', 'status_changed_at', 'status_changed_by', 'preferred_language'];

    protected $casts = [
        'status_changed_at' => 'datetime',
    ];

    public function contacts(): HasMany { return $this->hasMany(CounterpartyContact::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function overrideRequests(): HasMany { return $this->hasMany(OverrideRequest::class); }
    public function counterpartyMerges(): HasMany { return $this->hasMany(CounterpartyMerge::class, 'target_counterparty_id'); }
}
