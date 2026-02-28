<?php

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
