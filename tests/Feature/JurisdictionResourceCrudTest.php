<?php

use App\Filament\Resources\JurisdictionResource\Pages\CreateJurisdiction;
use App\Filament\Resources\JurisdictionResource\Pages\EditJurisdiction;
use App\Models\Jurisdiction;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render jurisdiction list page', function () {
    $this->get('/admin/jurisdictions')->assertSuccessful();
});

it('can create a jurisdiction via Livewire form', function () {
    Livewire::test(CreateJurisdiction::class)
        ->fillForm([
            'name' => 'UAE - DIFC',
            'country_code' => 'AE',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('jurisdictions', [
        'name' => 'UAE - DIFC',
        'country_code' => 'AE',
    ]);
});

it('validates required fields on jurisdiction create', function () {
    Livewire::test(CreateJurisdiction::class)
        ->fillForm([
            'name' => null,
            'country_code' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'country_code' => 'required',
        ]);
});

it('can edit a jurisdiction via Livewire form', function () {
    $jurisdiction = Jurisdiction::create([
        'name' => 'Before Edit',
        'country_code' => 'XX',
        'is_active' => true,
    ]);

    Livewire::test(EditJurisdiction::class, ['record' => $jurisdiction->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
            'regulatory_body' => 'Test Authority',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $jurisdiction->refresh();
    expect($jurisdiction->name)->toBe('After Edit');
    expect($jurisdiction->regulatory_body)->toBe('Test Authority');
});

it('blocks non-admin roles from creating jurisdictions', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/jurisdictions/create')->assertForbidden();
});
