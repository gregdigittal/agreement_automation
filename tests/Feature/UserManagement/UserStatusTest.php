<?php

use App\Mail\UserApprovedMail;
use App\Mail\UserInviteMail;
use App\Models\User;
use Filament\Facades\Filament;

// 1. Active user can access panel
it('allows active user with role to access panel', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('system_admin');

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

// 2. Pending user cannot access panel
it('blocks pending user from accessing panel', function () {
    $user = User::factory()->create(['status' => 'pending']);
    $user->assignRole('system_admin');

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

// 3. Suspended user cannot access panel
it('blocks suspended user from accessing panel', function () {
    $user = User::factory()->create(['status' => 'suspended']);
    $user->assignRole('system_admin');

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

// 4. Active user without role cannot access panel
it('blocks active user without roles from accessing panel', function () {
    $user = User::factory()->create(['status' => 'active']);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

// 5. Status defaults to active
it('defaults user status to active', function () {
    $user = User::factory()->create();

    expect($user->status)->toBe('active');
});

// 6. Invite mail is renderable
it('renders user invite mail', function () {
    $user = User::factory()->create();
    $mail = new UserInviteMail($user, ['legal', 'commercial']);

    expect($mail->envelope()->subject)->toBe("You've been granted access to CCRS");
    $rendered = $mail->render();
    expect($rendered)->toContain('legal, commercial');
});

// 7. Approved mail is renderable
it('renders user approved mail', function () {
    $user = User::factory()->create();
    $mail = new UserApprovedMail($user, ['legal']);

    expect($mail->envelope()->subject)->toBe('Your CCRS access has been approved');
    $rendered = $mail->render();
    expect($rendered)->toContain('legal');
});
