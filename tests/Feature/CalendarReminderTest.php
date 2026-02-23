<?php

namespace Tests\Feature;

use App\Mail\ContractReminderCalendar;
use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\Reminder;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_ics_content_is_valid(): void
    {
        $contract = Contract::factory()->create(['title' => 'Test Contract']);
        $keyDate  = ContractKeyDate::factory()->create([
            'contract_id' => $contract->id,
            'date_type'   => 'expiry',
            'date_value'  => '2027-06-30',
        ]);
        $reminder = Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'key_date_id'     => $keyDate->id,
            'lead_days'       => 30,
            'channel'         => 'calendar',
            'recipient_email' => 'test@example.com',
        ]);

        $ics = app(CalendarService::class)->generateIcs($reminder, $contract, $keyDate);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20270630', $ics);
        $this->assertStringContainsString('Test Contract', $ics);
    }

    public function test_calendar_reminder_email_sends_ics_attachment(): void
    {
        Mail::fake();

        $contract = Contract::factory()->create();
        $keyDate  = ContractKeyDate::factory()->create(['contract_id' => $contract->id]);
        $reminder = Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'key_date_id'     => $keyDate->id,
            'channel'         => 'calendar',
            'recipient_email' => 'legal@digittal.io',
        ]);

        Mail::to('legal@digittal.io')
            ->send(new ContractReminderCalendar($reminder, $contract, $keyDate));

        Mail::assertSent(ContractReminderCalendar::class, fn ($mail) =>
            $mail->hasTo('legal@digittal.io')
        );
    }
}
