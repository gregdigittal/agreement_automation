<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\Reminder;
use App\Services\NotificationService;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. Due reminder — notification created, timestamps updated
    // -------------------------------------------------------------------------

    public function test_process_reminders_sends_due_reminders(): void
    {
        $contract = Contract::factory()->create(['title' => 'Alpha Contract']);

        $nextDueAt = now()->subHour();

        $reminder = Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'is_active'       => true,
            'channel'         => 'email',
            'recipient_email' => 'legal@example.com',
            'reminder_type'   => 'expiry',
            'lead_days'       => 14,
            'next_due_at'     => $nextDueAt,
            'last_sent_at'    => null,
        ]);

        $count = app(ReminderService::class)->processReminders();

        // Service must report exactly one processed reminder.
        $this->assertSame(1, $count);

        // A Notification row must exist for the recipient.
        $this->assertDatabaseHas('notifications', [
            'recipient_email'        => 'legal@example.com',
            'channel'                => 'email',
            'related_resource_type'  => 'contract',
            'related_resource_id'    => $contract->id,
            'notification_category'  => 'reminders',
            'status'                 => 'pending',
        ]);

        // last_sent_at should now be set (approximately now).
        $reminder->refresh();
        $this->assertNotNull($reminder->last_sent_at);
        $this->assertTrue($reminder->last_sent_at->greaterThanOrEqualTo(Carbon::now()->subMinute()));

        // next_due_at should have been advanced by lead_days (14 days from the
        // original next_due_at, not from now, matching the service implementation).
        $expectedNext = Carbon::parse($nextDueAt)->addDays(14);
        $this->assertTrue(
            $reminder->next_due_at->eq($expectedNext) || $reminder->next_due_at->diffInSeconds($expectedNext) < 2,
            "next_due_at should be advanced by lead_days from the original next_due_at"
        );
    }

    // -------------------------------------------------------------------------
    // 2. Inactive reminder — must be skipped entirely
    // -------------------------------------------------------------------------

    public function test_process_reminders_skips_inactive(): void
    {
        $contract = Contract::factory()->create();

        Reminder::factory()->create([
            'contract_id'  => $contract->id,
            'is_active'    => false,
            'next_due_at'  => now()->subHour(),
            'last_sent_at' => null,
        ]);

        $count = app(ReminderService::class)->processReminders();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('notifications', 0);
    }

    // -------------------------------------------------------------------------
    // 3. Future reminder — next_due_at is in the future, must be skipped
    // -------------------------------------------------------------------------

    public function test_process_reminders_skips_future(): void
    {
        $contract = Contract::factory()->create();

        Reminder::factory()->create([
            'contract_id'  => $contract->id,
            'is_active'    => true,
            'next_due_at'  => now()->addDays(7),
            'last_sent_at' => null,
        ]);

        $count = app(ReminderService::class)->processReminders();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('notifications', 0);
    }

    // -------------------------------------------------------------------------
    // 4. Already-sent reminder — last_sent_at >= next_due_at, must be skipped
    // -------------------------------------------------------------------------

    public function test_process_reminders_skips_already_sent(): void
    {
        $contract = Contract::factory()->create();

        // next_due_at is in the past but last_sent_at equals next_due_at,
        // meaning the reminder was already dispatched for this cycle.
        $nextDueAt = now()->subHour();

        Reminder::factory()->create([
            'contract_id'  => $contract->id,
            'is_active'    => true,
            'next_due_at'  => $nextDueAt,
            'last_sent_at' => $nextDueAt, // last_sent_at == next_due_at → skip
        ]);

        $count = app(ReminderService::class)->processReminders();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('notifications', 0);
    }

    // -------------------------------------------------------------------------
    // 5. last_sent_at is strictly after next_due_at — also already sent, skip
    // -------------------------------------------------------------------------

    public function test_process_reminders_skips_when_last_sent_after_next_due(): void
    {
        $contract = Contract::factory()->create();

        $nextDueAt = now()->subDays(2);

        Reminder::factory()->create([
            'contract_id'  => $contract->id,
            'is_active'    => true,
            'next_due_at'  => $nextDueAt,
            'last_sent_at' => now()->subDay(), // sent after next_due_at → skip
        ]);

        $count = app(ReminderService::class)->processReminders();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('notifications', 0);
    }

    // -------------------------------------------------------------------------
    // 6. Multiple reminders — only due ones are processed
    // -------------------------------------------------------------------------

    public function test_process_reminders_handles_mixed_batch(): void
    {
        $contract = Contract::factory()->create();

        // Due reminder — should be processed.
        Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'is_active'       => true,
            'next_due_at'     => now()->subMinutes(30),
            'last_sent_at'    => null,
            'recipient_email' => 'due@example.com',
        ]);

        // Inactive reminder — should be skipped.
        Reminder::factory()->create([
            'contract_id'  => $contract->id,
            'is_active'    => false,
            'next_due_at'  => now()->subMinutes(30),
            'last_sent_at' => null,
        ]);

        // Future reminder — should be skipped.
        Reminder::factory()->create([
            'contract_id'  => $contract->id,
            'is_active'    => true,
            'next_due_at'  => now()->addDay(),
            'last_sent_at' => null,
        ]);

        // Already-sent reminder — should be skipped.
        $due = now()->subHours(2);
        Reminder::factory()->create([
            'contract_id'  => $contract->id,
            'is_active'    => true,
            'next_due_at'  => $due,
            'last_sent_at' => $due,
        ]);

        $count = app(ReminderService::class)->processReminders();

        $this->assertSame(1, $count);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['recipient_email' => 'due@example.com']);
    }

    // -------------------------------------------------------------------------
    // 7. Notification subject and body format
    // -------------------------------------------------------------------------

    public function test_process_reminders_creates_correct_notification_content(): void
    {
        $contract = Contract::factory()->create(['title' => 'Beta Contract']);

        Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'is_active'       => true,
            'next_due_at'     => now()->subMinutes(5),
            'last_sent_at'    => null,
            'reminder_type'   => 'renewal',
            'lead_days'       => 30,
            'recipient_email' => 'vendor@example.com',
        ]);

        app(ReminderService::class)->processReminders();

        $notification = Notification::where('recipient_email', 'vendor@example.com')->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('renewal', $notification->subject);
        $this->assertStringContainsString('Beta Contract', $notification->body);
        $this->assertStringContainsString('renewal', $notification->body);
        $this->assertStringContainsString('30', $notification->body);
    }

    // -------------------------------------------------------------------------
    // 8. NotificationService::create is called via the container (mock approach)
    // -------------------------------------------------------------------------

    public function test_process_reminders_delegates_to_notification_service(): void
    {
        $contract = Contract::factory()->create(['title' => 'Mock Contract']);

        Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'is_active'       => true,
            'next_due_at'     => now()->subMinutes(5),
            'last_sent_at'    => null,
            'channel'         => 'email',
            'recipient_email' => 'admin@example.com',
            'reminder_type'   => 'expiry',
            'lead_days'       => 7,
        ]);

        $mockService = $this->mock(NotificationService::class);
        $mockService->shouldReceive('create')
            ->once()
            ->with(\Mockery::on(function (array $data) use ($contract) {
                return $data['recipient_email'] === 'admin@example.com'
                    && $data['channel'] === 'email'
                    && $data['related_resource_id'] === $contract->id
                    && $data['notification_category'] === 'reminders';
            }))
            ->andReturn(new Notification([
                'id'                    => \Illuminate\Support\Str::uuid()->toString(),
                'recipient_email'       => 'admin@example.com',
                'channel'               => 'email',
                'subject'               => 'Reminder: expiry',
                'body'                  => 'Contract: Mock Contract.',
                'related_resource_type' => 'contract',
                'related_resource_id'   => $contract->id,
                'notification_category' => 'reminders',
                'status'                => 'pending',
            ]));

        $count = app(ReminderService::class)->processReminders();

        $this->assertSame(1, $count);
    }
}
