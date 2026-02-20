<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Counterparty extends Model
{
    use HasUuidPrimaryKey;
    use Searchable;

    protected $fillable = ['legal_name', 'registration_number', 'address', 'jurisdiction', 'status', 'status_reason', 'status_changed_at', 'status_changed_by', 'preferred_language'];

    protected $casts = [
        'status_changed_at' => 'datetime',
    ];

    public function toSearchableArray(): array
    {
        return [
            'legal_name' => $this->legal_name,
            'registration_number' => $this->registration_number,
            'jurisdiction' => $this->jurisdiction,
            'status' => $this->status,
        ];
    }

    public function contacts(): HasMany { return $this->hasMany(CounterpartyContact::class); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function overrideRequests(): HasMany { return $this->hasMany(OverrideRequest::class); }
    public function counterpartyMerges(): HasMany { return $this->hasMany(CounterpartyMerge::class, 'target_counterparty_id'); }
    public function vendorDocuments(): HasMany { return $this->hasMany(VendorDocument::class); }
    public function vendorUsers(): HasMany { return $this->hasMany(VendorUser::class); }
}
