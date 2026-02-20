<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('ccrs.azure_ad.group_map', [
        'legal-group-id' => 'legal',
    ]);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

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

it('creates user and assigns role on callback when user is in mapped group', function () {
    $azureId = 'azure-' . uniqid();
    $email = 'user@example.com';
    $name = 'Test User';

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response([
            'value' => [
                ['id' => 'legal-group-id', 'displayName' => 'Legal'],
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
    expect($user->hasRole('legal'))->toBeTrue();
});

it('rejects callback when user has no matching Azure group', function () {
    $azureId = 'azure-no-group';
    Http::fake([
        'https://graph.microsoft.com/v1.0/me/memberOf*' => Http::response(['value' => []], 200),
    ]);


    $mockSocialiteUser = new class($azureId, 'nogroup@example.com', 'No Group') implements \Laravel\Socialite\Contracts\User {
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
