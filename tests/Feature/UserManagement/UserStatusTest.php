<?php

use App\Mail\UserApprovedMail;
use App\Mail\UserInviteMail;
use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

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

// 8. First-time SSO user without pre-provisioned record gets pending status
it('creates pending user for first-time SSO without pre-provisioned record', function () {
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'fake-token';
    $socialiteUser->shouldReceive('getId')->andReturn('azure-uuid-123');
    $socialiteUser->shouldReceive('getEmail')->andReturn('newuser@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('New User');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get(route('azure.callback'));

    $this->assertDatabaseHas('users', [
        'id' => 'azure-uuid-123',
        'email' => 'newuser@example.com',
        'status' => 'pending',
    ]);
    $response->assertViewIs('auth.pending-approval');
});

// 9. Pre-provisioned active user logs in successfully via SSO
it('logs in pre-provisioned active user via SSO', function () {
    $user = User::factory()->create([
        'id' => 'azure-uuid-456',
        'email' => 'existing@example.com',
        'status' => 'active',
    ]);
    $user->assignRole('legal');

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'fake-token';
    $socialiteUser->shouldReceive('getId')->andReturn('azure-uuid-456');
    $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Existing User');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertRedirect('/admin');
    $this->assertAuthenticatedAs($user);
});

// 10. Pending user is shown pending screen on subsequent SSO
it('shows pending screen for pending user on subsequent SSO', function () {
    $user = User::factory()->create([
        'id' => 'azure-uuid-789',
        'email' => 'pending@example.com',
        'status' => 'pending',
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'fake-token';
    $socialiteUser->shouldReceive('getId')->andReturn('azure-uuid-789');
    $socialiteUser->shouldReceive('getEmail')->andReturn('pending@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Pending User');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertViewIs('auth.pending-approval');
    $this->assertGuest();
});

// 11. Suspended user is denied access
it('denies access to suspended user via SSO', function () {
    $user = User::factory()->create([
        'id' => 'azure-uuid-000',
        'email' => 'suspended@example.com',
        'status' => 'suspended',
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'fake-token';
    $socialiteUser->shouldReceive('getId')->andReturn('azure-uuid-000');
    $socialiteUser->shouldReceive('getEmail')->andReturn('suspended@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Suspended User');

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertRedirect('/admin/login');
});
