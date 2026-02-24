<?php

namespace App\Mail;

use App\Models\SigningSessionSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SigningInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SigningSessionSigner $signer) {}

    public function envelope(): Envelope
    {
        $contractTitle = $this->signer->session->contract->title ?? 'Contract';

        return new Envelope(subject: "Please sign: {$contractTitle}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signing-invitation');
    }
}
