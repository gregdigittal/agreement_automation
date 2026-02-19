<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\Reminder;
use App\Services\CalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractReminderCalendar extends Mailable
{
    use Queueable, SerializesModels;

    public string $icsContent;

    public function __construct(
        public readonly Reminder $reminder,
        public readonly Contract $contract,
        public readonly ContractKeyDate $keyDate,
    ) {
        $this->icsContent = app(CalendarService::class)->generateIcs($reminder, $contract, $keyDate);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Calendar Reminder: ' . $this->contract->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contract-reminder-calendar', with: [
            'contractTitle' => $this->contract->title,
            'dateType' => $this->keyDate->date_type,
            'dateValue' => $this->keyDate->date_value,
            'leadDays' => $this->reminder->lead_days,
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->icsContent, 'reminder.ics')->withMime('text/calendar'),
        ];
    }
}
