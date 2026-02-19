<?php

use App\Models\Counterparty;
use App\Models\User;

beforeEach(function () {
    config(['ccrs.tito_api_key' => 'test-tito-key-123']);
    $this->user = User::create(['id' => 'test-user', 'email' => 'test@example.com', 'name' => 'Test']);
});

it('rejects requests without API key', function () {
    $this->getJson('/api/tito/validate?registration_number=REG001')
        ->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized']);
});

it('rejects requests with wrong API key', function () {
    $this->getJson('/api/tito/validate?registration_number=REG001', [
        'X-TiTo-API-Key' => 'wrong-key',
    ])->assertStatus(401);
});

it('returns no match for unknown registration', function () {
    $this->getJson('/api/tito/validate?registration_number=UNKNOWN999', [
        'X-TiTo-API-Key' => 'test-tito-key-123',
    ])
        ->assertOk()
        ->assertJson([
            'match' => false,
            'source' => 'internal',
        ]);
});

it('returns match for known counterparty', function () {
    Counterparty::create([
        'legal_name' => 'Acme Corp',
        'registration_number' => 'ACN123456',
        'status' => 'Active',
        'jurisdiction' => 'AU',
    ]);

    $this->getJson('/api/tito/validate?registration_number=ACN123456', [
        'X-TiTo-API-Key' => 'test-tito-key-123',
    ])
        ->assertOk()
        ->assertJson([
            'match' => true,
            'source' => 'internal',
            'counterparty' => [
                'legal_name' => 'Acme Corp',
                'status' => 'Active',
            ],
        ]);
});

it('caches results for 5 minutes', function () {
    Counterparty::create([
        'legal_name' => 'Cache Corp',
        'registration_number' => 'CACHE001',
        'status' => 'Active',
    ]);

    $response1 = $this->getJson('/api/tito/validate?registration_number=CACHE001', [
        'X-TiTo-API-Key' => 'test-tito-key-123',
    ])->assertOk();

    Counterparty::where('registration_number', 'CACHE001')->delete();

    $response2 = $this->getJson('/api/tito/validate?registration_number=CACHE001', [
        'X-TiTo-API-Key' => 'test-tito-key-123',
    ])->assertOk();

    expect($response2->json('match'))->toBeTrue();
});
