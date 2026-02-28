<?php

use App\Models\User;

it('redirects to Azure for login', function () {
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

it('creates user with pending status on first-time SSO callback', function () {
    $azureId = 'azure-' . uniqid();
    $email = 'user@example.com';
    $name = 'Test User';

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

it('shows pending-approval view for first-time user regardless of Azure groups', function () {
    $azureId = 'azure-no-group';
    $email = 'nogroup@example.com';
    $name = 'No Group';

    $mockSocialiteUser = new class($azureId, $email, $name) implements \Laravel\Socialite\Contracts\User {
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

    $response->assertStatus(403);
    $response->assertViewIs('auth.pending-approval');

    $user = User::find($azureId);
    expect($user)->not->toBeNull();
    expect($user->status->value)->toBe('pending');
    expect($user->roles()->count())->toBe(0);

    // User should NOT be authenticated
    $this->assertGuest();
});
