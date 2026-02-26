<?php

use App\Filament\Resources\RegionResource\Pages\CreateRegion;
use App\Filament\Resources\RegionResource\Pages\EditRegion;
use App\Models\Region;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render region list page', function () {
    $this->get('/admin/regions')->assertSuccessful();
});

it('can create a region via Livewire form', function () {
    Livewire::test(CreateRegion::class)
        ->fillForm([
            'name' => 'Middle East',
            'code' => 'ME',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('regions', [
        'name' => 'Middle East',
        'code' => 'ME',
    ]);
});

it('validates required name on region create', function () {
    Livewire::test(CreateRegion::class)
        ->fillForm([
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
        ]);
});

it('can edit a region via Livewire form', function () {
    $region = Region::create(['name' => 'Before Edit', 'code' => 'BE']);

    Livewire::test(EditRegion::class, ['record' => $region->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($region->fresh()->name)->toBe('After Edit');
});

it('blocks non-admin roles from creating regions', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/regions/create')->assertForbidden();
});
