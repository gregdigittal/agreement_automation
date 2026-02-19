<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterpartyContact extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['counterparty_id', 'name', 'email', 'role', 'is_signer'];
    protected $casts = ['is_signer' => 'boolean'];

    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
}
