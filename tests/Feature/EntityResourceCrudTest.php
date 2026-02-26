<?php

use App\Filament\Resources\EntityResource\Pages\CreateEntity;
use App\Filament\Resources\EntityResource\Pages\EditEntity;
use App\Models\Entity;
use App\Models\Region;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Entity Test Region', 'code' => 'ER']);
});

it('can render entity list page', function () {
    $this->get('/admin/entities')->assertSuccessful();
});

it('can render entity create page', function () {
    $this->get('/admin/entities/create')->assertSuccessful();
});

it('can create an entity via Livewire form', function () {
    Livewire::test(CreateEntity::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'name' => 'New Test Entity',
            'code' => 'NTE',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('entities', [
        'name' => 'New Test Entity',
        'code' => 'NTE',
        'region_id' => $this->region->id,
    ]);
});

it('can create an entity with legal details', function () {
    Livewire::test(CreateEntity::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'name' => 'Legal Entity',
            'code' => 'LE',
            'legal_name' => 'Legal Entity Holdings Ltd',
            'registration_number' => 'REG-12345',
            'registered_address' => '123 Legal Street, Dubai',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('entities', [
        'name' => 'Legal Entity',
        'legal_name' => 'Legal Entity Holdings Ltd',
        'registration_number' => 'REG-12345',
    ]);
});

it('validates required fields on entity create', function () {
    Livewire::test(CreateEntity::class)
        ->fillForm([
            'region_id' => null,
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'region_id' => 'required',
            'name' => 'required',
        ]);
});

it('can edit an entity via Livewire form', function () {
    $entity = Entity::create([
        'region_id' => $this->region->id,
        'name' => 'Before Edit',
        'code' => 'BE',
    ]);

    Livewire::test(EditEntity::class, ['record' => $entity->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($entity->fresh()->name)->toBe('After Edit');
});

it('allows system_admin to create entities', function () {
    $this->get('/admin/entities/create')->assertSuccessful();
});

it('blocks non-admin roles from creating entities', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/entities/create')->assertForbidden();
});
