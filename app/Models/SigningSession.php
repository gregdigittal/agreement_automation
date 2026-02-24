<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SigningSession extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'contract_id', 'initiated_by', 'signing_order', 'status',
        'document_hash', 'final_document_hash', 'final_storage_path',
        'expires_at', 'completed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function initiator(): BelongsTo { return $this->belongsTo(User::class, 'initiated_by'); }
    public function signers(): HasMany { return $this->hasMany(SigningSessionSigner::class)->orderBy('signing_order'); }
    public function fields(): HasMany { return $this->hasMany(SigningField::class); }
    public function auditLog(): HasMany { return $this->hasMany(SigningAuditLog::class)->orderBy('created_at'); }
}
