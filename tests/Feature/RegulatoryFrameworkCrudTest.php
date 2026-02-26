<?php

use App\Filament\Resources\RegulatoryFrameworkResource\Pages\CreateRegulatoryFramework;
use App\Filament\Resources\RegulatoryFrameworkResource\Pages\EditRegulatoryFramework;
use App\Models\RegulatoryFramework;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    config(['features.regulatory_compliance' => true]);
});

it('can render regulatory framework list page when feature enabled', function () {
    $this->get('/admin/regulatory-frameworks')->assertSuccessful();
});

it('returns 403 when feature disabled', function () {
    config(['features.regulatory_compliance' => false]);

    $this->get('/admin/regulatory-frameworks')->assertForbidden();
});

it('can create a regulatory framework via Livewire form', function () {
    Livewire::test(CreateRegulatoryFramework::class)
        ->fillForm([
            'jurisdiction_code' => 'EU',
            'framework_name' => 'GDPR Data Processing',
            'description' => 'General Data Protection Regulation requirements',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('regulatory_frameworks', [
        'jurisdiction_code' => 'EU',
        'framework_name' => 'GDPR Data Processing',
    ]);
});

it('validates required fields on framework create', function () {
    Livewire::test(CreateRegulatoryFramework::class)
        ->fillForm([
            'jurisdiction_code' => null,
            'framework_name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'jurisdiction_code' => 'required',
            'framework_name' => 'required',
        ]);
});

it('can edit a regulatory framework via Livewire form', function () {
    $framework = RegulatoryFramework::create([
        'jurisdiction_code' => 'US',
        'framework_name' => 'Before Edit',
        'is_active' => true,
        'requirements' => [],
    ]);

    Livewire::test(EditRegulatoryFramework::class, ['record' => $framework->getRouteKey()])
        ->fillForm([
            'framework_name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($framework->fresh()->framework_name)->toBe('After Edit');
});

it('legal user can access when feature enabled', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/regulatory-frameworks')->assertSuccessful();
});

it('commercial user cannot access regulatory frameworks', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/regulatory-frameworks')->assertForbidden();
});
