<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SigningField extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'signing_session_id', 'assigned_to_signer_id', 'field_type',
        'label', 'page_number', 'x_position', 'y_position',
        'width', 'height', 'is_required', 'options', 'value', 'filled_at',
    ];

    protected $casts = [
        'page_number' => 'integer',
        'x_position' => 'decimal:2',
        'y_position' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_required' => 'boolean',
        'options' => 'array',
        'filled_at' => 'datetime',
    ];

    public function session(): BelongsTo { return $this->belongsTo(SigningSession::class, 'signing_session_id'); }
    public function signer(): BelongsTo { return $this->belongsTo(SigningSessionSigner::class, 'assigned_to_signer_id'); }
}
