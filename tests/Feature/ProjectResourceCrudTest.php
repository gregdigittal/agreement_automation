<?php

use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Project Test Region', 'code' => 'PR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Project Test Entity', 'code' => 'PE']);
});

it('can render project list page', function () {
    $this->get('/admin/projects')->assertSuccessful();
});

it('can create a project via Livewire form', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'entity_id' => $this->entity->id,
            'name' => 'New Test Project',
            'code' => 'NTP',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('projects', [
        'name' => 'New Test Project',
        'code' => 'NTP',
        'entity_id' => $this->entity->id,
    ]);
});

it('validates required fields on project create', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'entity_id' => null,
            'name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'entity_id' => 'required',
            'name' => 'required',
        ]);
});

it('can edit a project via Livewire form', function () {
    $project = Project::create([
        'entity_id' => $this->entity->id,
        'name' => 'Before Edit',
        'code' => 'BE',
    ]);

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($project->fresh()->name)->toBe('After Edit');
});

it('blocks non-admin roles from creating projects', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/projects/create')->assertForbidden();
});

it('can render project create page', function () {
    $this->get('/admin/projects/create')->assertSuccessful();
});
