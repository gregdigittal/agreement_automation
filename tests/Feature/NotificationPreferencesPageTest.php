<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render notification preferences page', function () {
    $this->get('/admin/notification-preferences-page')->assertSuccessful();
});

it('can save global email toggle', function () {
    $this->admin->update([
        'notification_preferences' => ['email' => true, 'teams' => true],
    ]);

    $this->admin->refresh();
    expect($this->admin->notification_preferences['email'])->toBeTrue();
});

it('can save per-category preferences', function () {
    $this->admin->update([
        'notification_preferences' => [
            'email' => true,
            'teams' => false,
            'workflow_actions' => ['email'],
            'escalations' => ['email', 'teams'],
        ],
    ]);

    $this->admin->refresh();
    $prefs = $this->admin->notification_preferences;
    expect($prefs['workflow_actions'])->toBe(['email']);
    expect($prefs['escalations'])->toContain('teams');
});

it('preferences persist across reloads', function () {
    $prefs = [
        'email' => true,
        'teams' => false,
        'reminders' => ['email', 'calendar'],
    ];
    $this->admin->update(['notification_preferences' => $prefs]);

    $fresh = User::find($this->admin->id);
    expect($fresh->notification_preferences)->toBe($prefs);
});

it('wantsNotification respects preferences', function () {
    $this->admin->update([
        'notification_preferences' => [
            'email' => false,
            'teams' => true,
        ],
    ]);

    $this->admin->refresh();
    expect($this->admin->wantsNotification('workflow_actions', 'email'))->toBeFalse();
});
