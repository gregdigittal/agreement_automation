<?php

namespace App\Mail;

use App\Models\SigningSessionSigner;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * IMPORTANT: This mailable must be sent synchronously (Mail::send), never queued.
 * The $rawToken is a plaintext signing token that must not be serialized to the jobs table.
 */
class SigningInvitation extends Mailable
{
    use SerializesModels;

    /**
     * @param  string  $rawToken  The unhashed token for URL construction (DB stores only the hash)
     */
    public function __construct(
        public SigningSessionSigner $signer,
        public string $rawToken = '',
    ) {}

    public function envelope(): Envelope
    {
        $contractTitle = $this->signer->session->contract->title ?? 'Contract';

        return new Envelope(subject: "Please sign: {$contractTitle}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signing-invitation',
            with: ['signingToken' => $this->rawToken],
        );
    }
}
