<?php

use App\Filament\Resources\CounterpartyResource\Pages\CreateCounterparty;
use App\Filament\Resources\CounterpartyResource\Pages\EditCounterparty;
use App\Models\Counterparty;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render create counterparty page', function () {
    $this->get('/admin/counterparties/create')->assertSuccessful();
});

it('can create a counterparty via Livewire form', function () {
    Livewire::test(CreateCounterparty::class)
        ->fillForm([
            'legal_name' => 'Livewire Created Corp',
            'registration_number' => 'REG-LW-001',
            'status' => 'Active',
            'jurisdiction' => 'UAE',
            'preferred_language' => 'en',
            'duplicate_acknowledged' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('counterparties', [
        'legal_name' => 'Livewire Created Corp',
        'registration_number' => 'REG-LW-001',
    ]);
});

it('validates required fields on counterparty create', function () {
    Livewire::test(CreateCounterparty::class)
        ->fillForm([
            'legal_name' => null,
            'status' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'legal_name' => 'required',
            'status' => 'required',
        ]);
});

it('can render edit counterparty page', function () {
    $cp = Counterparty::create([
        'legal_name' => 'Edit Page Corp',
        'status' => 'Active',
    ]);

    $this->get("/admin/counterparties/{$cp->id}/edit")->assertSuccessful();
});

it('can update a counterparty via Livewire form', function () {
    $cp = Counterparty::create([
        'legal_name' => 'Before Update Corp',
        'status' => 'Active',
        'jurisdiction' => 'UK',
    ]);

    Livewire::test(EditCounterparty::class, ['record' => $cp->getRouteKey()])
        ->fillForm([
            'legal_name' => 'After Update Corp',
            'jurisdiction' => 'UAE',
            'duplicate_acknowledged' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $cp->refresh();
    expect($cp->legal_name)->toBe('After Update Corp');
    expect($cp->jurisdiction)->toBe('UAE');
});

it('can update counterparty status to Suspended with reason', function () {
    $cp = Counterparty::create([
        'legal_name' => 'Status Change Corp',
        'status' => 'Active',
    ]);

    Livewire::test(EditCounterparty::class, ['record' => $cp->getRouteKey()])
        ->fillForm([
            'status' => 'Suspended',
            'status_reason' => 'Compliance review pending',
            'duplicate_acknowledged' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $cp->refresh();
    expect($cp->status)->toBe('Suspended');
    expect($cp->status_reason)->toBe('Compliance review pending');
});

it('commercial user can create counterparties', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/counterparties/create')->assertSuccessful();
});

it('audit user cannot create counterparties', function () {
    $audit = User::factory()->create();
    $audit->assignRole('audit');
    $this->actingAs($audit);

    $this->get('/admin/counterparties/create')->assertForbidden();
});
