<?php
namespace App\Mail;

use App\Models\VendorUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly VendorUser $vendor, public readonly string $mailSubject, public readonly string $body) {}

    public function envelope(): Envelope { return new Envelope(subject: $this->mailSubject); }

    public function content(): Content
    {
        return new Content(markdown: 'mail.vendor-notification', with: [
            'vendorName' => $this->vendor->name, 'body' => $this->body, 'subject' => $this->mailSubject,
            'portalUrl' => config('app.url') . '/vendor',
        ]);
    }
}
