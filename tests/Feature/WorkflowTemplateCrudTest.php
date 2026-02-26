<?php

use App\Filament\Resources\WorkflowTemplateResource\Pages\CreateWorkflowTemplate;
use App\Filament\Resources\WorkflowTemplateResource\Pages\EditWorkflowTemplate;
use App\Filament\Resources\WorkflowTemplateResource\Pages\ListWorkflowTemplates;
use App\Models\Region;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render workflow template list page', function () {
    $this->get('/admin/workflow-templates')->assertSuccessful();
});

it('can render workflow template create page', function () {
    $this->get('/admin/workflow-templates/create')->assertSuccessful();
});

it('can create a workflow template via Livewire form', function () {
    Livewire::test(CreateWorkflowTemplate::class)
        ->fillForm([
            'name' => 'Standard Commercial Workflow',
            'contract_type' => 'Commercial',
            'status' => 'draft',
            'stages' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('workflow_templates', [
        'name' => 'Standard Commercial Workflow',
        'contract_type' => 'Commercial',
        'status' => 'draft',
    ]);
});

it('validates required fields on workflow template create', function () {
    Livewire::test(CreateWorkflowTemplate::class)
        ->fillForm([
            'name' => null,
            'contract_type' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'contract_type' => 'required',
        ]);
});

it('can edit a workflow template via Livewire form', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Before Edit',
        'contract_type' => 'Commercial',
        'status' => 'draft',
    ]);

    Livewire::test(EditWorkflowTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->name)->toBe('After Edit');
});

it('can create template with optional region', function () {
    $region = Region::create(['name' => 'WT Region', 'code' => 'WR']);

    Livewire::test(CreateWorkflowTemplate::class)
        ->fillForm([
            'name' => 'Regional Workflow',
            'contract_type' => 'Merchant',
            'region_id' => $region->id,
            'status' => 'draft',
            'stages' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('workflow_templates', [
        'name' => 'Regional Workflow',
        'region_id' => $region->id,
    ]);
});

it('publish action sets status to published and increments version', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Publishable Template',
        'contract_type' => 'Commercial',
        'status' => 'draft',
        'version' => 1,
        'stages' => [['name' => 'Draft', 'role' => 'legal', 'order' => 1]],
    ]);

    Livewire::test(ListWorkflowTemplates::class)
        ->callTableAction('publish', $template);

    $template->refresh();
    expect($template->status)->toBe('published');
    expect($template->version)->toBe(2);
    expect($template->published_at)->not->toBeNull();
});

it('publish action blocked when stages are empty', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Empty Stages Template',
        'contract_type' => 'Commercial',
        'status' => 'draft',
        'version' => 1,
        'stages' => [],
    ]);

    Livewire::test(ListWorkflowTemplates::class)
        ->callTableAction('publish', $template);

    $template->refresh();
    expect($template->status)->toBe('draft');
});

it('blocks non-admin roles from creating workflow templates', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/workflow-templates/create')->assertForbidden();
});
