<?php

namespace App\Mail;

use App\Models\ExchangeRoomPost;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExchangeRoomNewVersionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExchangeRoomPost $post,
        public readonly string $contractTitle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Document Version: {$this->contractTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.exchange-room-new-version',
            with: [
                'authorName' => $this->post->author_name,
                'actorSide' => $this->post->actor_side === 'internal' ? 'Digittal' : 'Vendor',
                'contractTitle' => $this->contractTitle,
                'fileName' => $this->post->file_name,
                'versionNumber' => $this->post->version_number,
                'portalUrl' => config('app.url'),
            ],
        );
    }
}
