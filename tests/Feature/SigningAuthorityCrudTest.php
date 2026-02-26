<?php

use App\Filament\Resources\SigningAuthorityResource\Pages\CreateSigningAuthority;
use App\Filament\Resources\SigningAuthorityResource\Pages\EditSigningAuthority;
use App\Models\Entity;
use App\Models\Region;
use App\Models\SigningAuthority;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'SA Region', 'code' => 'SR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'SA Entity', 'code' => 'SE']);
});

it('can render signing authority list page', function () {
    $this->get('/admin/signing-authorities')->assertSuccessful();
});

it('can render signing authority create page', function () {
    $this->get('/admin/signing-authorities/create')->assertSuccessful();
});

it('can create a signing authority via Livewire form', function () {
    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => $this->entity->id,
            'user_id' => $this->admin->id,
            'user_email' => 'signer@digittal.io',
            'role_or_name' => 'General Counsel',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('signing_authority', [
        'entity_id' => $this->entity->id,
        'user_email' => 'signer@digittal.io',
        'role_or_name' => 'General Counsel',
    ]);
});

it('validates required fields on signing authority create', function () {
    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => null,
            'user_email' => null,
            'role_or_name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'entity_id' => 'required',
            'user_email' => 'required',
            'role_or_name' => 'required',
        ]);
});

it('can edit a signing authority via Livewire form', function () {
    $sa = SigningAuthority::create([
        'entity_id' => $this->entity->id,
        'user_id' => $this->admin->id,
        'user_email' => 'old@digittal.io',
        'role_or_name' => 'CFO',
    ]);

    Livewire::test(EditSigningAuthority::class, ['record' => $sa->getRouteKey()])
        ->fillForm([
            'role_or_name' => 'CEO',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($sa->fresh()->role_or_name)->toBe('CEO');
});

it('blocks non-admin roles from creating signing authorities', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/signing-authorities/create')->assertForbidden();
});
