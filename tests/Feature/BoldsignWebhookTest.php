<?php

/**
 * Tests for the BoldSign webhook controller (deprecated legacy path).
 *
 * These tests manually register the BoldSign webhook route because the route
 * is only registered in production when FEATURE_IN_HOUSE_SIGNING=false.
 * Since the default is now true, the route is not available during normal
 * test bootstrapping.
 */

use App\Http\Controllers\Webhooks\BoldsignWebhookController;
use App\Services\BoldsignService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Disable in-house signing so BoldSign tests are meaningful
    Config::set('ccrs.in_house_signing', false);
    Config::set('ccrs.boldsign_webhook_secret', 'test-webhook-secret');

    // Manually register the BoldSign webhook route for these tests, since the
    // route is conditionally registered only when in-house signing is disabled
    // at boot time and the default is now true.
    // The route now includes {tenant_slug} — each tenant configures BoldSign with
    // its own webhook URL (e.g. acme-ccrs.digittal.mobi/webhooks/boldsign/acme).
    Route::post('/api/webhooks/boldsign/{tenant_slug}', [BoldsignWebhookController::class, 'handle'])
        ->name('test.webhooks.boldsign');
});

it('returns 401 when webhook signature is invalid', function () {
    $payload = ['documentId' => 'doc-1', 'event' => 'Completed'];
    $invalidSignature = 'invalid-hmac';

    $response = $this->postJson('/api/webhooks/boldsign/acme', $payload, [
        'X-BoldSign-Signature' => $invalidSignature,
        'Content-Type' => 'application/json',
    ]);

    $response->assertStatus(401);
});

it('returns 200 and ok when webhook signature is valid', function () {
    $payload = ['documentId' => 'doc-1', 'event' => 'Completed'];
    $rawBody = json_encode($payload);
    $validSignature = hash_hmac('sha256', $rawBody, 'test-webhook-secret');

    $this->mock(BoldsignService::class, function ($mock) {
        $mock->shouldReceive('verifyWebhookSignature')
            ->once()
            ->andReturn(true);
        $mock->shouldReceive('handleWebhook')
            ->once()
            ->with(\Mockery::on(fn ($arg) => isset($arg['documentId']) && $arg['documentId'] === 'doc-1'));
    });

    $response = $this->postJson('/api/webhooks/boldsign/acme', $payload, [
        'X-BoldSign-Signature' => $validSignature,
        'Content-Type' => 'application/json',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
});

it('webhook route accepts a tenant_slug path segment for multi-tenant BoldSign configuration', function () {
    // Each tenant configures BoldSign with its own webhook URL:
    //   https://{slug}-ccrs.digittal.mobi/webhooks/boldsign/{slug}
    // The route parameter is {tenant_slug} — confirm different slugs are routable.
    $this->mock(BoldsignService::class, function ($mock) {
        $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $mock->shouldReceive('handleWebhook')->once();
    });

    $payload = ['documentId' => 'doc-tenant-test', 'event' => 'Completed'];

    // Hit with a different slug — route should still resolve.
    $response = $this->postJson('/api/webhooks/boldsign/beta-corp', $payload, [
        'X-BoldSign-Signature' => 'any',
        'Content-Type' => 'application/json',
    ]);

    // 200 expected (mock bypasses signature check)
    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
});
