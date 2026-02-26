<?php

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;

beforeEach(function () {
    $this->service = app(NotificationService::class);
    $this->user = User::factory()->create();
});

it('can create a notification', function () {
    $notif = $this->service->create([
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
        'channel' => 'email',
        'status' => 'pending',
    ]);
});

it('can list notifications for a user', function () {
    $this->service->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Notif 1',
        'body' => 'Body 1',
    ]);
    $this->service->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Notif 2',
        'body' => 'Body 2',
    ]);

    $results = $this->service->listNotifications($this->user);
    expect($results)->toHaveCount(2);
});

it('can mark a notification as read', function () {
    $notif = $this->service->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Read Me',
        'body' => 'Body',
    ]);

    expect($notif->read_at)->toBeNull();

    $this->service->markRead($notif->id, $this->user);
    $notif->refresh();

    expect($notif->read_at)->not->toBeNull();
});

it('can mark all notifications as read', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->service->create([
            'recipient_user_id' => $this->user->id,
            'recipient_email' => $this->user->email,
            'channel' => 'email',
            'subject' => "Notif $i",
            'body' => "Body $i",
        ]);
    }

    $count = $this->service->markAllRead($this->user);
    expect($count)->toBe(3);

    $unread = Notification::where('recipient_user_id', $this->user->id)
        ->whereNull('read_at')
        ->count();
    expect($unread)->toBe(0);
});

it('unread filter returns only unread notifications', function () {
    $notif1 = $this->service->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Already Read',
        'body' => 'Body',
    ]);
    $this->service->markRead($notif1->id, $this->user);

    $this->service->create([
        'recipient_user_id' => $this->user->id,
        'recipient_email' => $this->user->email,
        'channel' => 'email',
        'subject' => 'Still Unread',
        'body' => 'Body',
    ]);

    $unreadOnly = $this->service->listNotifications($this->user, unreadOnly: true);
    expect($unreadOnly)->toHaveCount(1);
    expect($unreadOnly->first()->subject)->toBe('Still Unread');
});
