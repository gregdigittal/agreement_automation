<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigningAuditLog extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;
    protected $table = 'signing_audit_log';

    protected $fillable = [
        'signing_session_id', 'signer_id', 'event',
        'details', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo { return $this->belongsTo(SigningSession::class, 'signing_session_id'); }
    public function signer(): BelongsTo { return $this->belongsTo(SigningSessionSigner::class, 'signer_id'); }
}
