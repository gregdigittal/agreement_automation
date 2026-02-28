<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
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

// 1. Admin can view user list
it('renders user list page for system admin', function () {
    Livewire::test(ListUsers::class)->assertSuccessful();
});

// 2. Non-admin cannot access user list
it('denies non-admin access to user list', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('commercial');
    $this->actingAs($user);

    Livewire::test(ListUsers::class)->assertForbidden();
});

// 3. Admin can create a user with roles
it('creates user with roles and sends invite email', function () {
    Mail::fake();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'roles' => ['legal', 'commercial'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'status' => 'active',
    ]);

    $user = User::where('email', 'jane@example.com')->first();
    expect($user->hasRole('legal'))->toBeTrue();
    expect($user->hasRole('commercial'))->toBeTrue();

    Mail::assertQueued(\App\Mail\UserInviteMail::class, function ($mail) {
        return $mail->hasTo('jane@example.com');
    });
});

// 4. Admin can edit user roles
it('updates user roles', function () {
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('commercial');

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['legal'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->hasRole('legal'))->toBeTrue();
    expect($user->hasRole('commercial'))->toBeFalse();
});

// 5. Pending users visible in list
it('shows pending users in list', function () {
    $pending = User::factory()->create(['status' => 'pending', 'name' => 'Pending Person']);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords(
            User::where('status', 'pending')->get()
        );
});
