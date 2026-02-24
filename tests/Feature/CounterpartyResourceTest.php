<?php

use App\Models\Counterparty;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);
});

it('can list counterparties', function () {
    Counterparty::create(['legal_name' => 'Acme Corp', 'status' => 'Active']);
    $this->get('/admin/counterparties')->assertSuccessful();
});

it('can create a counterparty', function () {
    Counterparty::create([
        'legal_name' => 'New Corp',
        'registration_number' => 'REG123',
        'status' => 'Active',
        'jurisdiction' => 'US',
    ]);

    expect(Counterparty::where('legal_name', 'New Corp')->exists())->toBeTrue();
});

it('tracks status changes', function () {
    $cp = Counterparty::create(['legal_name' => 'Status Corp', 'status' => 'Active']);
    $cp->update(['status' => 'Suspended', 'status_reason' => 'Review needed']);
    expect($cp->fresh()->status)->toBe('Suspended');
    expect($cp->fresh()->status_reason)->toBe('Review needed');
});
