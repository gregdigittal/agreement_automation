<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoldsignEnvelope extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = ['contract_id', 'boldsign_document_id', 'status', 'is_countersign', 'signing_order', 'signers', 'webhook_payload', 'sent_at', 'completed_at', 'created_by'];
    protected $casts = ['signers' => 'array', 'webhook_payload' => 'array', 'is_countersign' => 'boolean', 'sent_at' => 'datetime', 'completed_at' => 'datetime'];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
