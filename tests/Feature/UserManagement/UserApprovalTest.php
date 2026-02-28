<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Mail\UserApprovedMail;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// 1. Approve action sets user to active and assigns roles
it('approves pending user with roles and sends email', function () {
    Mail::fake();

    $pending = User::factory()->create(['status' => 'pending', 'email' => 'pending@example.com']);

    Livewire::test(ListUsers::class)
        ->callTableAction('approve', $pending, data: [
            'roles' => ['legal'],
        ]);

    $pending->refresh();
    expect($pending->status->value)->toBe('active');
    expect($pending->hasRole('legal'))->toBeTrue();

    Mail::assertQueued(UserApprovedMail::class, function ($mail) {
        return $mail->hasTo('pending@example.com');
    });
});

// 2. Suspend action sets user to suspended
it('suspends an active user', function () {
    $active = User::factory()->create(['status' => 'active']);
    $active->assignRole('legal');

    Livewire::test(ListUsers::class)
        ->callTableAction('suspend', $active);

    $active->refresh();
    expect($active->status->value)->toBe('suspended');
});

// 3. Reactivate action sets user back to active
it('reactivates a suspended user', function () {
    $suspended = User::factory()->create(['status' => 'suspended']);
    $suspended->assignRole('legal');

    Livewire::test(ListUsers::class)
        ->callTableAction('reactivate', $suspended);

    $suspended->refresh();
    expect($suspended->status->value)->toBe('active');
});

// 4. Reject action deletes the pending user
it('rejects and deletes a pending user', function () {
    $pending = User::factory()->create(['status' => 'pending']);

    Livewire::test(ListUsers::class)
        ->callTableAction('reject', $pending);

    $this->assertDatabaseMissing('users', ['id' => $pending->id]);
});
