<?php

use App\Mail\ContractReminderCalendar;
use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\EscalationEvent;
use App\Models\EscalationRule;
use App\Models\Notification;
use App\Models\Reminder;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\CalendarService;
use App\Services\EscalationService;
use App\Services\NotificationService;
use App\Services\ReminderService;
use App\Services\TeamsNotificationService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->notificationService = app(NotificationService::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// PREFERENCES (1-4)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 1. Notification preferences page renders
// ---------------------------------------------------------------------------
it('can render notification preferences page', function () {
    $this->get('/admin/notification-preferences-page')->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 2. Can save global channel toggle
// ---------------------------------------------------------------------------
it('can save global email toggle preference', function () {
    $this->user->update([
        'notification_preferences' => ['email' => true, 'teams' => false],
    ]);

    $this->user->refresh();
    expect($this->user->notification_preferences['email'])->toBeTrue();
    expect($this->user->notification_preferences['teams'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// 3. Can save per-category preferences
// ---------------------------------------------------------------------------
it('can save per-category notification preferences', function () {
    $this->user->update([
        'notification_preferences' => [
            'email' => true,
            'teams' => true,
            'workflow_actions' => ['email'],
            'escalations' => ['email', 'teams'],
            'reminders' => ['email', 'calendar'],
        ],
    ]);

    $this->user->refresh();
    $prefs = $this->user->notification_preferences;

    expect($prefs['workflow_actions'])->toBe(['email']);
    expect($prefs['escalations'])->toContain('teams');
    expect($prefs['reminders'])->toContain('calendar');
});

// ---------------------------------------------------------------------------
// 4. wantsNotification respects disabled channel
// ---------------------------------------------------------------------------
it('wantsNotification returns false when channel globally disabled', function () {
    $this->user->update([
        'notification_preferences' => ['email' => false, 'teams' => true],
    ]);

    $this->user->refresh();

    expect($this->user->wantsNotification('workflow_actions', 'email'))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// IN-APP NOTIFICATIONS (5-9)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 5. Can create a notification (inbox entry)
// ---------------------------------------------------------------------------
it('can create a notification for the inbox', function () {
    $notif = $this->notificationService->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Test Notification',
        'body' => 'This is a test body',
        'notification_category' => 'workflow_actions',
    ]);

    expect($notif)->toBeInstanceOf(Notification::class);
    $this->assertDatabaseHas('notifications', [
        'recipient_user_id' => $this->user->id,
        'subject' => 'Test Notification',
        'status' => 'pending',
    ]);
});

// ---------------------------------------------------------------------------
// 6. Can list notifications for a user
// ---------------------------------------------------------------------------
it('can list notifications for a user', function () {
    $this->notificationService->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'First',
        'body' => 'Body 1',
    ]);
    $this->notificationService->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Second',
        'body' => 'Body 2',
    ]);

    $results = $this->notificationService->listNotifications($this->user);
    expect($results)->toHaveCount(2);
});

// ---------------------------------------------------------------------------
// 7. Read status is tracked
// ---------------------------------------------------------------------------
it('tracks notification read status', function () {
    $notif = $this->notificationService->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Read Me',
        'body' => 'Body',
    ]);

    expect($notif->read_at)->toBeNull();

    $this->notificationService->markRead($notif->id, $this->user);
    $notif->refresh();

    expect($notif->read_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 8. Mark all notifications as read
// ---------------------------------------------------------------------------
it('can mark all notifications as read', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->notificationService->create([
            'recipient_user_id' => $this->user->id,
            'recipient_email' => $this->user->email,
            'channel' => 'email',
            'subject' => "Notif $i",
            'body' => "Body $i",
        ]);
    }

    $count = $this->notificationService->markAllRead($this->user);
    expect($count)->toBe(3);

    $unread = Notification::where('recipient_user_id', $this->user->id)
        ->whereNull('read_at')
        ->count();
    expect($unread)->toBe(0);
});

// ---------------------------------------------------------------------------
// 9. Badge count: unread filter returns only unread notifications
// ---------------------------------------------------------------------------
it('unread filter returns only unread notifications for badge count', function () {
    $notif1 = $this->notificationService->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Already Read',
        'body' => 'Body',
    ]);
    $this->notificationService->markRead($notif1->id, $this->user);

    $this->notificationService->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Still Unread',
        'body' => 'Body',
    ]);

    $unreadOnly = $this->notificationService->listNotifications($this->user, unreadOnly: true);
    expect($unreadOnly)->toHaveCount(1);
    expect($unreadOnly->first()->subject)->toBe('Still Unread');
});

// ═══════════════════════════════════════════════════════════════════════════
// KEY DATES (10-12)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 10. Can create key dates for a contract
// ---------------------------------------------------------------------------
it('can create key dates for a contract', function () {
    $contract = Contract::factory()->create();

    $keyDate = ContractKeyDate::factory()->create([
        'contract_id' => $contract->id,
        'date_type' => 'expiry',
        'date_value' => '2027-12-31',
        'description' => 'Contract expiry date',
    ]);

    $this->assertDatabaseHas('contract_key_dates', [
        'contract_id' => $contract->id,
        'date_type' => 'expiry',
    ]);

    expect($keyDate->contract_id)->toBe($contract->id);
});

// ---------------------------------------------------------------------------
// 11. Key dates page renders with filter list
// ---------------------------------------------------------------------------
it('key dates page renders successfully', function () {
    $this->get('/admin/key-dates-page')->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 12. Key dates can be filtered by date type
// ---------------------------------------------------------------------------
it('key dates can be filtered by date type', function () {
    $contract = Contract::factory()->create();

    ContractKeyDate::factory()->create([
        'contract_id' => $contract->id,
        'date_type' => 'expiry',
        'date_value' => now()->addDays(30),
    ]);

    ContractKeyDate::factory()->create([
        'contract_id' => $contract->id,
        'date_type' => 'renewal',
        'date_value' => now()->addDays(60),
    ]);

    $expiryDates = ContractKeyDate::where('date_type', 'expiry')->get();
    $renewalDates = ContractKeyDate::where('date_type', 'renewal')->get();

    expect($expiryDates)->toHaveCount(1);
    expect($renewalDates)->toHaveCount(1);
});

// ═══════════════════════════════════════════════════════════════════════════
// REMINDERS (13-16)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 13. Reminder linked to a key date
// ---------------------------------------------------------------------------
it('reminder can be linked to a key date', function () {
    $contract = Contract::factory()->create();
    $keyDate = ContractKeyDate::factory()->create([
        'contract_id' => $contract->id,
        'date_type' => 'expiry',
        'date_value' => '2027-06-30',
    ]);

    $reminder = Reminder::factory()->create([
        'contract_id' => $contract->id,
        'key_date_id' => $keyDate->id,
        'reminder_type' => 'expiry',
        'lead_days' => 30,
        'channel' => 'email',
        'recipient_email' => 'legal@example.com',
    ]);

    expect($reminder->key_date_id)->toBe($keyDate->id);
    expect($reminder->keyDate->date_type)->toBe('expiry');
});

// ---------------------------------------------------------------------------
// 14. Due reminder dispatches notification
// ---------------------------------------------------------------------------
it('processes due reminders and creates notifications', function () {
    $contract = Contract::factory()->create(['title' => 'Dispatch Test']);

    Reminder::factory()->create([
        'contract_id' => $contract->id,
        'is_active' => true,
        'channel' => 'email',
        'recipient_email' => 'legal@example.com',
        'reminder_type' => 'expiry',
        'lead_days' => 14,
        'next_due_at' => now()->subHour(),
        'last_sent_at' => null,
    ]);

    $count = app(ReminderService::class)->processReminders();

    expect($count)->toBe(1);

    $this->assertDatabaseHas('notifications', [
        'recipient_email' => 'legal@example.com',
        'channel' => 'email',
        'related_resource_type' => 'contract',
        'related_resource_id' => $contract->id,
        'notification_category' => 'reminders',
    ]);
});

// ---------------------------------------------------------------------------
// 15. Duplicate prevention: already-sent reminder is skipped
// ---------------------------------------------------------------------------
it('prevents duplicate reminders when already sent', function () {
    $contract = Contract::factory()->create();
    $nextDueAt = now()->subHour();

    Reminder::factory()->create([
        'contract_id' => $contract->id,
        'is_active' => true,
        'next_due_at' => $nextDueAt,
        'last_sent_at' => $nextDueAt,
    ]);

    $count = app(ReminderService::class)->processReminders();

    expect($count)->toBe(0);
    $this->assertDatabaseCount('notifications', 0);
});

// ---------------------------------------------------------------------------
// 16. Inactive reminder is skipped
// ---------------------------------------------------------------------------
it('skips inactive reminders during processing', function () {
    $contract = Contract::factory()->create();

    Reminder::factory()->create([
        'contract_id' => $contract->id,
        'is_active' => false,
        'next_due_at' => now()->subHour(),
        'last_sent_at' => null,
    ]);

    $count = app(ReminderService::class)->processReminders();

    expect($count)->toBe(0);
    $this->assertDatabaseCount('notifications', 0);
});

// ═══════════════════════════════════════════════════════════════════════════
// ESCALATION (17-18)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 17. SLA breach creates escalation notification
// ---------------------------------------------------------------------------
it('SLA breach creates escalation event', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn(new \App\Models\Notification());
    });

    $contract = Contract::factory()->create();

    $template = WorkflowTemplate::create([
        'name' => 'Esc Template',
        'contract_type' => 'Commercial',
        'version' => 1,
        'status' => 'published',
        'stages' => [['name' => 'Legal Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    $rule = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Legal Review',
        'sla_breach_hours' => 24,
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Legal Review',
        'state' => 'active',
        'started_at' => now()->subHours(30),
    ]);

    $count = app(EscalationService::class)->checkSlaBreaches();

    expect($count)->toBe(1);
    $this->assertDatabaseHas('escalation_events', [
        'workflow_instance_id' => $instance->id,
        'rule_id' => $rule->id,
        'tier' => 1,
    ]);
});

// ---------------------------------------------------------------------------
// 18. Tier progression: duplicate escalations are not created
// ---------------------------------------------------------------------------
it('does not duplicate unresolved escalation events for same tier', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn(new \App\Models\Notification());
    });

    $contract = Contract::factory()->create();

    $template = WorkflowTemplate::create([
        'name' => 'Tier Template',
        'contract_type' => 'Commercial',
        'version' => 1,
        'status' => 'published',
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    $rule = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 24,
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Review',
        'state' => 'active',
        'started_at' => now()->subHours(30),
    ]);

    // Pre-seed an existing unresolved escalation
    EscalationEvent::create([
        'workflow_instance_id' => $instance->id,
        'rule_id' => $rule->id,
        'contract_id' => $contract->id,
        'stage_name' => 'Review',
        'tier' => 1,
        'escalated_at' => now()->subHours(6),
        'created_at' => now()->subHours(6),
        'resolved_at' => null,
    ]);

    $count = app(EscalationService::class)->checkSlaBreaches();

    expect($count)->toBe(0);
    $this->assertDatabaseCount('escalation_events', 1);
});

// ═══════════════════════════════════════════════════════════════════════════
// TEAMS WEBHOOK (19)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 19. Teams webhook posts message to channel
// ---------------------------------------------------------------------------
it('sends message to Teams channel via webhook', function () {
    config([
        'ccrs.teams.team_id' => 'test-team-id',
        'ccrs.teams.channel_id' => 'test-channel-id',
        'ccrs.teams.token_endpoint' => 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token',
        'ccrs.teams.graph_base_url' => 'https://graph.microsoft.com/v1.0',
        'services.azure.client_id' => 'client-id',
        'services.azure.client_secret' => 'client-secret',
    ]);

    Http::fake([
        '*oauth2/v2.0/token*' => Http::response(['access_token' => 'fake-token'], 200),
        '*teams/*/channels/*/messages*' => Http::response(['id' => 'msg-123'], 201),
    ]);

    app(TeamsNotificationService::class)->sendToChannel(
        'Contract Approved',
        'Contract XYZ has been approved by Legal.'
    );

    Http::assertSent(function ($req) {
        if (! str_contains($req->url(), '/messages')) {
            return false;
        }
        $content = $req->data()['body']['content'] ?? '';
        return str_contains($content, 'Contract Approved');
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// CALENDAR / .ICS (20)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 20. ICS file generation for calendar reminders
// ---------------------------------------------------------------------------
it('generates valid ICS file content for calendar reminder', function () {
    $contract = Contract::factory()->create(['title' => 'ICS Test Contract']);
    $keyDate = ContractKeyDate::factory()->create([
        'contract_id' => $contract->id,
        'date_type' => 'expiry',
        'date_value' => '2027-06-30',
    ]);
    $reminder = Reminder::factory()->create([
        'contract_id' => $contract->id,
        'key_date_id' => $keyDate->id,
        'lead_days' => 30,
        'channel' => 'calendar',
        'recipient_email' => 'test@example.com',
    ]);

    $ics = app(CalendarService::class)->generateIcs($reminder, $contract, $keyDate);

    expect($ics)->toContain('BEGIN:VCALENDAR');
    expect($ics)->toContain('BEGIN:VEVENT');
    expect($ics)->toContain('DTSTART;VALUE=DATE:20270630');
    expect($ics)->toContain('ICS Test Contract');
});
