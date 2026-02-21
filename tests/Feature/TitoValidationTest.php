<?php

use App\Models\BoldsignEnvelope;
use App\Models\Contract;
use App\Models\Counterparty;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['ccrs.tito_api_key' => 'test-tito-key']);
});

it('returns 401 without api key', function () {
    $response = $this->getJson('/api/tito/validate?vendor_id=' . Str::uuid());
    $response->assertStatus(401)->assertJson(['error' => 'Unauthorized']);
});

it('returns invalid when no signed agreement', function () {
    $vendorId = Str::uuid()->toString();

    $response = $this->withHeader('X-TiTo-API-Key', 'test-tito-key')
        ->getJson("/api/tito/validate?vendor_id={$vendorId}");

    $response->assertStatus(200)
        ->assertJson([
            'valid'  => false,
            'status' => 'no_signed_agreement',
        ]);
});

it('returns valid when signed agreement exists', function () {
    $counterparty = Counterparty::factory()->create();
    $contract = Contract::factory()->create([
        'contract_type'    => 'Merchant',
        'counterparty_id'  => $counterparty->id,
    ]);
    BoldsignEnvelope::factory()->create([
        'contract_id' => $contract->id,
        'status'      => 'completed',
    ]);

    $response = $this->withHeader('X-TiTo-API-Key', 'test-tito-key')
        ->getJson("/api/tito/validate?vendor_id={$counterparty->id}");

    $response->assertStatus(200)
        ->assertJson([
            'valid'       => true,
            'status'      => 'signed',
            'contract_id' => $contract->id,
        ]);
});

it('caches result for five minutes', function () {
    $vendorId = Str::uuid()->toString();

    $r1 = $this->withHeader('X-TiTo-API-Key', 'test-tito-key')
        ->getJson("/api/tito/validate?vendor_id={$vendorId}");
    $r1->assertJson(['valid' => false]);

    $r2 = $this->withHeader('X-TiTo-API-Key', 'test-tito-key')
        ->getJson("/api/tito/validate?vendor_id={$vendorId}");
    $r2->assertJson(['valid' => false]);
});
