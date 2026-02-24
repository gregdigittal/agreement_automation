<?php

namespace App\Mail;

use App\Models\SigningSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SigningComplete extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SigningSession $session) {}

    public function envelope(): Envelope
    {
        $contractTitle = $this->session->contract->title ?? 'Contract';

        return new Envelope(subject: "Signing complete: {$contractTitle}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signing-complete');
    }
}
