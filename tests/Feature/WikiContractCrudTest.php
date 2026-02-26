<?php

use App\Filament\Resources\WikiContractResource\Pages\CreateWikiContract;
use App\Filament\Resources\WikiContractResource\Pages\EditWikiContract;
use App\Models\Region;
use App\Models\User;
use App\Models\WikiContract;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render wiki contract list page', function () {
    $this->get('/admin/wiki-contracts')->assertSuccessful();
});

it('can render wiki contract create page', function () {
    $this->get('/admin/wiki-contracts/create')->assertSuccessful();
});

it('can create a wiki contract via Livewire form', function () {
    $region = Region::create(['name' => 'Wiki Region', 'code' => 'WR']);

    Livewire::test(CreateWikiContract::class)
        ->fillForm([
            'name' => 'Standard NDA Template',
            'category' => 'NDA',
            'region_id' => $region->id,
            'description' => 'Standard non-disclosure agreement template',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('wiki_contracts', [
        'name' => 'Standard NDA Template',
        'category' => 'NDA',
        'status' => 'draft',
    ]);
});

it('validates required name on wiki contract create', function () {
    Livewire::test(CreateWikiContract::class)
        ->fillForm([
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
        ]);
});

it('can edit a wiki contract via Livewire form', function () {
    $wiki = WikiContract::create([
        'name' => 'Before Edit',
        'status' => 'draft',
    ]);

    Livewire::test(EditWikiContract::class, ['record' => $wiki->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
            'status' => 'published',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $wiki->refresh();
    expect($wiki->name)->toBe('After Edit');
    expect($wiki->status)->toBe('published');
});

it('legal user can create wiki contracts', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/wiki-contracts/create')->assertSuccessful();
});

it('commercial user cannot create wiki contracts', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/wiki-contracts/create')->assertForbidden();
});
