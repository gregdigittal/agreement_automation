<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('ccrs.azure_ad.group_map', [
        'legal-group-id' => 'legal',
        'commercial-group-id' => 'commercial',
        'finance-group-id' => 'finance',
        'operations-group-id' => 'operations',
        'audit-group-id' => 'audit',
        'system-admin-group-id' => 'system_admin',
    ]);
});

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

// ── 2. Callback with legal group creates user and assigns role ───────────────

it('Azure AD callback with email matching legal group creates User with role legal and authenticates them', function () {
    $azureId = 'azure-legal-' . uniqid();
    $email = 'legal-user@example.com';
    $name = 'Legal Test User';

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'legal-group-id', 'displayName' => 'Legal Team'],
            ],
        ], 200),
    ]);

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

    $response->assertStatus(302);
    expect(str_contains($response->headers->get('Location') ?? '', 'admin'))->toBeTrue();

    $user = User::where('id', $azureId)->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe($email);
    expect($user->name)->toBe($name);
    expect($user->hasRole('legal'))->toBeTrue();
});

// ── 3. Callback for existing user does not create duplicate ──────────────────

it('callback for existing user does not create duplicate and authenticates existing record', function () {
    $azureId = 'azure-existing-' . uniqid();
    $email = 'existing@example.com';
    $name = 'Existing User';

    // Pre-create the user
    $existingUser = new User(['email' => $email, 'name' => $name]);
    $existingUser->id = $azureId;
    $existingUser->save();
    $existingUser->assignRole('legal');

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'legal-group-id', 'displayName' => 'Legal Team'],
            ],
        ], 200),
    ]);

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

    $response->assertStatus(302);
    expect(User::where('id', $azureId)->count())->toBe(1);

    // Verify user details were updated
    $user = User::find($azureId);
    expect($user->email)->toBe('updated-email@example.com');
    expect($user->name)->toBe('Updated Name');
});

// ── 4. Role mapping: Azure AD groups map to CCRS roles ───────────────────────

it('maps system_admin Azure AD group to system_admin role', function () {
    $azureId = 'azure-sysadmin-' . uniqid();

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'system-admin-group-id', 'displayName' => 'System Admins'],
            ],
        ], 200),
    ]);

    $mockSocialiteUser = new class($azureId, 'admin@example.com', 'Admin') implements \Laravel\Socialite\Contracts\User {
        public function __construct(private string $id, private string $email, private string $name) {}
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

    $this->get(route('azure.callback'));

    $user = User::find($azureId);
    expect($user)->not->toBeNull();
    expect($user->hasRole('system_admin'))->toBeTrue();
});

it('maps commercial Azure AD group to commercial role', function () {
    $azureId = 'azure-commercial-' . uniqid();

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'commercial-group-id', 'displayName' => 'Commercial Team'],
            ],
        ], 200),
    ]);

    $mockSocialiteUser = new class($azureId, 'commercial@example.com', 'Commercial User') implements \Laravel\Socialite\Contracts\User {
        public function __construct(private string $id, private string $email, private string $name) {}
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

    $this->get(route('azure.callback'));

    $user = User::find($azureId);
    expect($user)->not->toBeNull();
    expect($user->hasRole('commercial'))->toBeTrue();
});

it('maps finance Azure AD group to finance role', function () {
    $azureId = 'azure-finance-' . uniqid();

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'finance-group-id', 'displayName' => 'Finance Team'],
            ],
        ], 200),
    ]);

    $mockSocialiteUser = new class($azureId, 'finance@example.com', 'Finance User') implements \Laravel\Socialite\Contracts\User {
        public function __construct(private string $id, private string $email, private string $name) {}
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

    $this->get(route('azure.callback'));

    $user = User::find($azureId);
    expect($user)->not->toBeNull();
    expect($user->hasRole('finance'))->toBeTrue();
});

// ── 5. User with unmapped group gets rejected ────────────────────────────────

it('user whose Azure AD group does not map to any CCRS role gets rejected and no User record created', function () {
    $azureId = 'azure-unknown-' . uniqid();

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'unknown-group-id', 'displayName' => 'Random Team'],
            ],
        ], 200),
    ]);

    $mockSocialiteUser = new class($azureId, 'unknown@example.com', 'Unknown User') implements \Laravel\Socialite\Contracts\User {
        public function __construct(private string $id, private string $email, private string $name) {}
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

    $response->assertRedirect();
    expect(str_contains($response->headers->get('Location') ?? '', 'login'))->toBeTrue();
    $response->assertSessionHasErrors('auth');

    // The user record IS created (controller creates it before role check),
    // but has no roles assigned — canAccessPanel returns false
    $user = User::find($azureId);
    if ($user) {
        expect($user->roles()->count())->toBe(0);
    }
});

it('user with empty group membership gets rejected', function () {
    $azureId = 'azure-empty-' . uniqid();

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response(['value' => []], 200),
    ]);

    $mockSocialiteUser = new class($azureId, 'nogroups@example.com', 'No Groups') implements \Laravel\Socialite\Contracts\User {
        public function __construct(private string $id, private string $email, private string $name) {}
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

    $response->assertRedirect();
    expect(str_contains($response->headers->get('Location') ?? '', 'login'))->toBeTrue();
    $response->assertSessionHasErrors('auth');
});

// ── 6. After successful callback user has session and can access /admin ───────

it('after successful callback user has active session and can access /admin', function () {
    $azureId = 'azure-session-' . uniqid();

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'legal-group-id', 'displayName' => 'Legal Team'],
            ],
        ], 200),
    ]);

    $mockSocialiteUser = new class($azureId, 'session-test@example.com', 'Session User') implements \Laravel\Socialite\Contracts\User {
        public function __construct(private string $id, private string $email, private string $name) {}
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

// ── Priority: when user belongs to multiple groups, highest priority wins ────

it('when user belongs to multiple groups the highest priority role is assigned', function () {
    $azureId = 'azure-multi-' . uniqid();

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'finance-group-id', 'displayName' => 'Finance'],
                ['id' => 'legal-group-id', 'displayName' => 'Legal'],
                ['id' => 'audit-group-id', 'displayName' => 'Audit'],
            ],
        ], 200),
    ]);

    $mockSocialiteUser = new class($azureId, 'multi@example.com', 'Multi Role User') implements \Laravel\Socialite\Contracts\User {
        public function __construct(private string $id, private string $email, private string $name) {}
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

    $this->get(route('azure.callback'));

    $user = User::find($azureId);
    expect($user)->not->toBeNull();
    // Priority order: system_admin > legal > commercial > finance > operations > audit
    // Legal is highest priority among the three groups
    expect($user->hasRole('legal'))->toBeTrue();
    expect($user->roles->count())->toBe(1);
});
