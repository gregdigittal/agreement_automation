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
    Route::post('/api/webhooks/boldsign', [BoldsignWebhookController::class, 'handle'])
        ->name('test.webhooks.boldsign');
});

it('returns 401 when webhook signature is invalid', function () {
    $payload = ['documentId' => 'doc-1', 'event' => 'Completed'];
    $invalidSignature = 'invalid-hmac';

    $response = $this->postJson('/api/webhooks/boldsign', $payload, [
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

    $response = $this->postJson('/api/webhooks/boldsign', $payload, [
        'X-BoldSign-Signature' => $validSignature,
        'Content-Type' => 'application/json',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
});
