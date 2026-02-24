<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    // Roles are seeded by TestCase::setUp()

    // -------------------------------------------------------------------------
    // ccrs:create-admin
    // -------------------------------------------------------------------------

    public function test_create_admin_creates_new_user(): void
    {
        $email = 'newadmin@example.com';

        $this->assertDatabaseMissing('users', ['email' => $email]);

        $this->artisan('ccrs:create-admin', ['email' => $email])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => $email]);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertTrue($user->hasRole('system_admin'));
    }

    public function test_create_admin_promotes_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $this->assertFalse($user->hasRole('system_admin'));

        $this->artisan('ccrs:create-admin', ['email' => $user->email])
            ->assertExitCode(0);

        $user->refresh();
        $this->assertTrue($user->hasRole('system_admin'));
    }

    public function test_create_admin_accepts_name_option(): void
    {
        $email = 'named@example.com';
        $name  = 'Greg Morris';

        $this->artisan('ccrs:create-admin', [
            'email'  => $email,
            '--name' => $name,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'name'  => $name,
        ]);
    }

    // -------------------------------------------------------------------------
    // queue:health
    // -------------------------------------------------------------------------

    public function test_queue_health_succeeds_when_redis_available(): void
    {
        Redis::shouldReceive('connection')
            ->once()
            ->with('queue')
            ->andReturnSelf();

        Redis::shouldReceive('ping')
            ->once()
            ->andReturn('+PONG');

        $this->artisan('queue:health')
            ->assertExitCode(0);
    }

    public function test_queue_health_fails_when_redis_unavailable(): void
    {
        Redis::shouldReceive('connection')
            ->once()
            ->with('queue')
            ->andThrow(new \Exception('Connection refused'));

        $this->artisan('queue:health')
            ->assertExitCode(1);
    }
}
