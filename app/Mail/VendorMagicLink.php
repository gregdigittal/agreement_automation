<?php

namespace App\Mail;

use App\Models\VendorUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorMagicLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly VendorUser $vendor,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your CCRS Vendor Portal Login Link');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.vendor-magic-link',
            with: [
                'vendorName' => $this->vendor->name,
                'link' => $this->link,
            ],
        );
    }
}
