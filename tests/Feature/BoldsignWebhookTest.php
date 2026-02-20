<?php

use App\Services\BoldsignService;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('ccrs.boldsign_webhook_secret', 'test-webhook-secret');
});

it('returns 401 when webhook signature is invalid', function () {
    $payload = ['documentId' => 'doc-1', 'event' => 'Completed'];
    $invalidSignature = 'invalid-hmac';

    $response = $this->postJson(route('webhooks.boldsign'), $payload, [
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

    $response = $this->postJson(route('webhooks.boldsign'), $payload, [
        'X-BoldSign-Signature' => $validSignature,
        'Content-Type' => 'application/json',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
});
