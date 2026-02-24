<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SigningSessionSigner extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'signing_session_id', 'signer_name', 'signer_email', 'signer_type',
        'signing_order', 'token', 'token_expires_at', 'status',
        'signature_image_path', 'signature_method',
        'ip_address', 'user_agent', 'signed_at', 'sent_at', 'viewed_at',
    ];

    // M3: Prevent token hash from leaking in JSON/array serialization
    protected $hidden = ['token'];

    protected $casts = [
        'signing_order' => 'integer',
        'token_expires_at' => 'datetime',
        'signed_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    public function session(): BelongsTo { return $this->belongsTo(SigningSession::class, 'signing_session_id'); }
    public function fields(): HasMany { return $this->hasMany(SigningField::class, 'assigned_to_signer_id'); }
}
