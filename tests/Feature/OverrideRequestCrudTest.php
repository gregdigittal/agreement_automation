<?php

use App\Filament\Resources\OverrideRequestResource\Pages\CreateOverrideRequest;
use App\Filament\Resources\OverrideRequestResource\Pages\EditOverrideRequest;
use App\Filament\Resources\OverrideRequestResource\Pages\ListOverrideRequests;
use App\Models\Counterparty;
use App\Models\OverrideRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->counterparty = Counterparty::create(['legal_name' => 'OR Test Corp', 'status' => 'Suspended']);
});

it('can render override request list page', function () {
    $this->get('/admin/override-requests')->assertSuccessful();
});

it('can render override request create page', function () {
    $this->get('/admin/override-requests/create')->assertSuccessful();
});

it('can create an override request directly', function () {
    $request = OverrideRequest::create([
        'counterparty_id' => $this->counterparty->id,
        'contract_title' => 'Urgent Contract',
        'requested_by_email' => $this->admin->email,
        'reason' => 'Business critical deal requires immediate processing',
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('override_requests', [
        'counterparty_id' => $this->counterparty->id,
        'contract_title' => 'Urgent Contract',
        'status' => 'pending',
    ]);
});

it('validates required fields on override request create', function () {
    Livewire::test(CreateOverrideRequest::class)
        ->fillForm([
            'counterparty_id' => null,
            'reason' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'counterparty_id' => 'required',
            'reason' => 'required',
        ]);
});

it('approve action sets status and decided fields', function () {
    $request = OverrideRequest::create([
        'counterparty_id' => $this->counterparty->id,
        'requested_by_email' => 'requester@test.com',
        'contract_title' => 'Test Contract',
        'reason' => 'Urgent deal',
        'status' => 'pending',
    ]);

    Livewire::test(ListOverrideRequests::class)
        ->callTableAction('approve', $request, ['comment' => 'Approved for Q1 deal']);

    $request->refresh();
    expect($request->status)->toBe('approved');
    expect($request->decided_by)->toBe($this->admin->email);
    expect($request->decided_at)->not->toBeNull();
    expect($request->comment)->toBe('Approved for Q1 deal');

    $this->assertDatabaseHas('audit_log', [
        'action' => 'override_approved',
        'resource_type' => 'override_request',
        'resource_id' => $request->id,
    ]);
});

it('reject action requires comment and sets status', function () {
    $request = OverrideRequest::create([
        'counterparty_id' => $this->counterparty->id,
        'requested_by_email' => 'requester@test.com',
        'contract_title' => 'Test Contract',
        'reason' => 'Urgent deal',
        'status' => 'pending',
    ]);

    Livewire::test(ListOverrideRequests::class)
        ->callTableAction('reject', $request, ['comment' => 'Does not meet compliance requirements']);

    $request->refresh();
    expect($request->status)->toBe('rejected');
    expect($request->decided_by)->toBe($this->admin->email);
    expect($request->comment)->toBe('Does not meet compliance requirements');

    $this->assertDatabaseHas('audit_log', [
        'action' => 'override_rejected',
        'resource_type' => 'override_request',
    ]);
});

it('commercial user cannot create override requests', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/override-requests/create')->assertForbidden();
});
