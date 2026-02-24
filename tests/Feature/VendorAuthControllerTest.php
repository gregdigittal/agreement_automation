<?php

namespace Tests\Feature;

use App\Models\VendorLoginToken;
use App\Models\VendorUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.vendor_portal' => true]);
    }

    public function test_request_link_creates_token_for_valid_vendor(): void
    {
        $vendor = VendorUser::factory()->create(['email' => 'vendor@example.com']);

        $response = $this->post(route('vendor.auth.request'), [
            'email' => 'vendor@example.com',
        ]);

        $response->assertSessionHas('status', 'If an account exists, a login link has been sent.');

        $this->assertDatabaseHas('vendor_login_tokens', [
            'vendor_user_id' => $vendor->id,
        ]);

        $this->assertNotNull(
            VendorLoginToken::where('vendor_user_id', $vendor->id)->first()
        );
    }

    public function test_request_link_returns_generic_message_for_unknown_email(): void
    {
        $response = $this->post(route('vendor.auth.request'), [
            'email' => 'nobody@unknown.example',
        ]);

        $response->assertSessionHas('status', 'If an account exists, a login link has been sent.');

        $this->assertDatabaseCount('vendor_login_tokens', 0);
    }

    public function test_verify_logs_in_vendor_with_valid_token(): void
    {
        $vendor = VendorUser::factory()->create();

        $rawToken = Str::random(64);
        VendorLoginToken::create([
            'id'             => Str::uuid()->toString(),
            'vendor_user_id' => $vendor->id,
            'token_hash'     => hash('sha256', $rawToken),
            'expires_at'     => now()->addMinutes(15),
            'created_at'     => now(),
        ]);

        $response = $this->get(route('vendor.auth.verify', ['token' => $rawToken]));

        $response->assertRedirect('/vendor');

        $this->assertTrue(Auth::guard('vendor')->check());
        $this->assertEquals($vendor->id, Auth::guard('vendor')->id());
    }

    public function test_verify_rejects_expired_token(): void
    {
        $vendor = VendorUser::factory()->create();

        $rawToken = Str::random(64);
        VendorLoginToken::create([
            'id'             => Str::uuid()->toString(),
            'vendor_user_id' => $vendor->id,
            'token_hash'     => hash('sha256', $rawToken),
            'expires_at'     => now()->subMinutes(5),
            'created_at'     => now()->subMinutes(20),
        ]);

        $response = $this->get(route('vendor.auth.verify', ['token' => $rawToken]));

        $response->assertRedirect(route('vendor.login'));
        $response->assertSessionHasErrors(['token' => 'Invalid or expired link.']);

        $this->assertFalse(Auth::guard('vendor')->check());
    }

    public function test_verify_rejects_used_token(): void
    {
        $vendor = VendorUser::factory()->create();

        $rawToken = Str::random(64);
        VendorLoginToken::create([
            'id'             => Str::uuid()->toString(),
            'vendor_user_id' => $vendor->id,
            'token_hash'     => hash('sha256', $rawToken),
            'expires_at'     => now()->addMinutes(15),
            'used_at'        => now()->subMinutes(1),
            'created_at'     => now()->subMinutes(2),
        ]);

        $response = $this->get(route('vendor.auth.verify', ['token' => $rawToken]));

        $response->assertRedirect(route('vendor.login'));
        $response->assertSessionHasErrors(['token' => 'Invalid or expired link.']);

        $this->assertFalse(Auth::guard('vendor')->check());
    }

    public function test_logout_destroys_session(): void
    {
        $vendor = VendorUser::factory()->create();

        $this->actingAs($vendor, 'vendor');

        $this->assertTrue(Auth::guard('vendor')->check());

        $response = $this->post(route('vendor.logout'));

        $response->assertRedirect(route('vendor.login'));

        $this->assertFalse(Auth::guard('vendor')->check());
    }
}
