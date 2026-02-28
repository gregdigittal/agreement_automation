<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly array $roles,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your CCRS access has been approved');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.user-approved',
            with: [
                'userName' => $this->user->name,
                'roleList' => implode(', ', $this->roles),
                'loginUrl' => config('app.url') . '/admin',
            ],
        );
    }
}
