<?php

use App\Models\User;

// ── 1. Redirect to Azure OAuth endpoint ──────────────────────────────────────

it('GET /auth/azure/redirect returns a redirect to Microsoft OAuth endpoint', function () {
    $redirectUrl = 'https://login.microsoftonline.com/common/oauth2/authorize';

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('scopes')
        ->andReturnSelf()
        ->shouldReceive('redirect')
        ->andReturn(redirect()->away($redirectUrl));

    $response = $this->get(route('azure.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('login.microsoftonline.com');
});

// ── 2. First-time SSO user is created with pending status ────────────────────

it('first-time SSO user is created with pending status and shown pending-approval view', function () {
    $azureId = 'azure-new-' . uniqid();
    $email = 'new-user@example.com';
    $name = 'New Test User';

    $mockSocialiteUser = new class($azureId, $email, $name) implements \Laravel\Socialite\Contracts\User {
        public function __construct(
            private string $id,
            private string $email,
            private string $name,
        ) {}
        public function getId() { return $this->id; }
        public function getNickname() { return null; }
        public function getName() { return $this->name; }
        public function getEmail() { return $this->email; }
        public function getAvatar() { return null; }
        public $token = 'fake-token';
    };

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn($mockSocialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertStatus(403);
    $response->assertViewIs('auth.pending-approval');

    $user = User::find($azureId);
    expect($user)->not->toBeNull();
    expect($user->email)->toBe($email);
    expect($user->name)->toBe($name);
    expect($user->status->value)->toBe('pending');
    expect($user->roles()->count())->toBe(0);
});

// ── 3. Existing active user with role logs in normally ───────────────────────

it('existing active user with role logs in and is redirected to admin', function () {
    $azureId = 'azure-active-' . uniqid();
    $email = 'active@example.com';
    $name = 'Active User';

    // Pre-create the user as active with a role
    $existingUser = new User(['email' => $email, 'name' => $name, 'status' => 'active']);
    $existingUser->id = $azureId;
    $existingUser->save();
    $existingUser->assignRole('legal');

    $mockSocialiteUser = new class($azureId, 'updated-email@example.com', 'Updated Name') implements \Laravel\Socialite\Contracts\User {
        public function __construct(
            private string $id,
            private string $email,
            private string $name,
        ) {}
        public function getId() { return $this->id; }
        public function getNickname() { return null; }
        public function getName() { return $this->name; }
        public function getEmail() { return $this->email; }
        public function getAvatar() { return null; }
        public $token = 'fake-token';
    };

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn($mockSocialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertRedirect('/admin');

    // Verify user details were updated
    $user = User::find($azureId);
    expect($user->email)->toBe('updated-email@example.com');
    expect($user->name)->toBe('Updated Name');

    // Verify no duplicate created
    expect(User::where('id', $azureId)->count())->toBe(1);

    // Verify authenticated
    $this->assertAuthenticatedAs($user);
});

// ── 4. Existing pending user is shown pending-approval view ──────────────────

it('existing pending user is shown pending-approval view on re-login', function () {
    $azureId = 'azure-pending-' . uniqid();
    $email = 'pending@example.com';
    $name = 'Pending User';

    // Pre-create user as pending (as if they logged in before but admin hasn't approved)
    $existingUser = new User(['email' => $email, 'name' => $name, 'status' => 'pending']);
    $existingUser->id = $azureId;
    $existingUser->save();

    $mockSocialiteUser = new class($azureId, $email, $name) implements \Laravel\Socialite\Contracts\User {
        public function __construct(
            private string $id,
            private string $email,
            private string $name,
        ) {}
        public function getId() { return $this->id; }
        public function getNickname() { return null; }
        public function getName() { return $this->name; }
        public function getEmail() { return $this->email; }
        public function getAvatar() { return null; }
        public $token = 'fake-token';
    };

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn($mockSocialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertStatus(403);
    $response->assertViewIs('auth.pending-approval');

    // User should NOT be authenticated
    $this->assertGuest();
});

// ── 5. Suspended user is redirected with error ───────────────────────────────

it('suspended user is redirected to login with suspension error', function () {
    $azureId = 'azure-suspended-' . uniqid();
    $email = 'suspended@example.com';
    $name = 'Suspended User';

    // Pre-create user as suspended
    $existingUser = new User(['email' => $email, 'name' => $name, 'status' => 'suspended']);
    $existingUser->id = $azureId;
    $existingUser->save();

    $mockSocialiteUser = new class($azureId, $email, $name) implements \Laravel\Socialite\Contracts\User {
        public function __construct(
            private string $id,
            private string $email,
            private string $name,
        ) {}
        public function getId() { return $this->id; }
        public function getNickname() { return null; }
        public function getName() { return $this->name; }
        public function getEmail() { return $this->email; }
        public function getAvatar() { return null; }
        public $token = 'fake-token';
    };

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn($mockSocialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertRedirect('/admin/login');
    $response->assertSessionHasErrors('auth');
});

// ── 6. Active user without roles is redirected with no-access error ──────────

it('active user without any roles is redirected to login with no-access error', function () {
    $azureId = 'azure-norole-' . uniqid();
    $email = 'norole@example.com';
    $name = 'No Role User';

    // Pre-create user as active but with no roles
    $existingUser = new User(['email' => $email, 'name' => $name, 'status' => 'active']);
    $existingUser->id = $azureId;
    $existingUser->save();

    $mockSocialiteUser = new class($azureId, $email, $name) implements \Laravel\Socialite\Contracts\User {
        public function __construct(
            private string $id,
            private string $email,
            private string $name,
        ) {}
        public function getId() { return $this->id; }
        public function getNickname() { return null; }
        public function getName() { return $this->name; }
        public function getEmail() { return $this->email; }
        public function getAvatar() { return null; }
        public $token = 'fake-token';
    };

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn($mockSocialiteUser);

    $response = $this->get(route('azure.callback'));

    $response->assertRedirect('/admin/login');
    $response->assertSessionHasErrors('auth');

    // User should NOT be authenticated
    $this->assertGuest();
});

// ── 7. Azure AD failure is handled gracefully ────────────────────────────────

it('handles Azure AD authentication failure gracefully', function () {
    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andThrow(new \Exception('OAuth token expired'));

    $response = $this->get(route('azure.callback'));

    $response->assertRedirect('/admin/login');
    $response->assertSessionHasErrors('auth');
});

// ── 8. After successful callback active user can access /admin ───────────────

it('after successful callback active user with role can access /admin', function () {
    $azureId = 'azure-session-' . uniqid();
    $email = 'session-test@example.com';
    $name = 'Session User';

    // Pre-create user as active with role
    $existingUser = new User(['email' => $email, 'name' => $name, 'status' => 'active']);
    $existingUser->id = $azureId;
    $existingUser->save();
    $existingUser->assignRole('legal');

    $mockSocialiteUser = new class($azureId, $email, $name) implements \Laravel\Socialite\Contracts\User {
        public function __construct(
            private string $id,
            private string $email,
            private string $name,
        ) {}
        public function getId() { return $this->id; }
        public function getNickname() { return null; }
        public function getName() { return $this->name; }
        public function getEmail() { return $this->email; }
        public function getAvatar() { return null; }
        public $token = 'fake-token';
    };

    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')
        ->with('azure')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn($mockSocialiteUser);

    // Perform callback (logs user in)
    $this->get(route('azure.callback'));

    // Now verify the user can access /admin (Filament panel)
    $response = $this->get('/admin');
    $response->assertSuccessful();

    // Verify user is authenticated
    $this->assertAuthenticatedAs(User::find($azureId));
});
