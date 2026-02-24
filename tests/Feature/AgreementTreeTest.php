<?php

use App\Livewire\AgreementTree;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\EntityJurisdiction;
use App\Models\Jurisdiction;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);

    $this->region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity', 'code' => 'TE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Test Project', 'code' => 'TP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Test Corp', 'status' => 'Active']);
});

it('renders the agreement repository page', function () {
    $this->get('/admin/agreement-repository')
        ->assertSuccessful()
        ->assertSeeLivewire(AgreementTree::class);
});

it('groups contracts by entity', function () {
    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Entity Draft Contract',
        'workflow_state' => 'draft',
    ]);
    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Merchant',
        'title' => 'Entity Executed Contract',
        'workflow_state' => 'executed',
    ]);

    $component = Livewire::test(AgreementTree::class);

    $component->assertSet('groupBy', 'entity');

    $treeData = $component->viewData('treeData');

    expect($treeData)->toBeArray();
    expect($treeData)->not->toBeEmpty();

    $entityNode = collect($treeData)->firstWhere('name', 'Test Entity');
    expect($entityNode)->not->toBeNull();
    expect($entityNode['type'])->toBe('entity');
    expect($entityNode['draft_count'])->toBe(1);
    expect($entityNode['executed_count'])->toBe(1);
    expect($entityNode['total_count'])->toBe(2);
});

it('groups contracts by counterparty', function () {
    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'CP Contract',
        'workflow_state' => 'draft',
    ]);

    $component = Livewire::test(AgreementTree::class)
        ->call('setGroupBy', 'counterparty');

    $component->assertSet('groupBy', 'counterparty');

    $treeData = $component->viewData('treeData');

    expect($treeData)->toBeArray();
    $cpNode = collect($treeData)->firstWhere('name', 'Test Corp');
    expect($cpNode)->not->toBeNull();
    expect($cpNode['type'])->toBe('counterparty');
    expect($cpNode['total_count'])->toBe(1);
});

it('filters by search term', function () {
    Entity::create(['region_id' => $this->region->id, 'name' => 'Alpha Entity', 'code' => 'AE']);
    Entity::create(['region_id' => $this->region->id, 'name' => 'Beta Entity', 'code' => 'BE']);

    $component = Livewire::test(AgreementTree::class)
        ->set('search', 'Alpha');

    $treeData = $component->viewData('treeData');

    expect($treeData)->toBeArray();

    $names = collect($treeData)->pluck('name')->toArray();
    expect($names)->toContain('Alpha Entity');
    expect($names)->not->toContain('Beta Entity');
});

it('shows correct count badges', function () {
    $draftContracts = 2;
    $executedContracts = 3;
    $inReviewContracts = 1;

    for ($i = 0; $i < $draftContracts; $i++) {
        Contract::create([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Commercial',
            'title' => "Draft Contract {$i}",
            'workflow_state' => 'draft',
        ]);
    }

    for ($i = 0; $i < $executedContracts; $i++) {
        Contract::create([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Commercial',
            'title' => "Executed Contract {$i}",
            'workflow_state' => 'executed',
        ]);
    }

    for ($i = 0; $i < $inReviewContracts; $i++) {
        Contract::create([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Merchant',
            'title' => "In Review Contract {$i}",
            'workflow_state' => 'in_review',
        ]);
    }

    $component = Livewire::test(AgreementTree::class);
    $treeData = $component->viewData('treeData');

    $entityNode = collect($treeData)->firstWhere('name', 'Test Entity');
    expect($entityNode)->not->toBeNull();
    expect($entityNode['draft_count'])->toBe($draftContracts);
    expect($entityNode['executed_count'])->toBe($executedContracts);
    expect($entityNode['active_count'])->toBe($inReviewContracts);
    expect($entityNode['total_count'])->toBe($draftContracts + $executedContracts + $inReviewContracts);
});

it('loads contracts when node is expanded', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $contract = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Loadable Contract',
        'workflow_state' => 'draft',
    ]);

    $component = Livewire::test(AgreementTree::class)
        ->call('toggleNode', 'entity_' . $this->entity->id)
        ->call('loadContracts', 'entity', $this->entity->id);

    $loaded = $component->get('loadedContracts');
    $nodeKey = 'entity_' . $this->entity->id;

    expect($loaded)->toHaveKey($nodeKey);
    expect($loaded[$nodeKey]['contracts'])->toHaveCount(1);
    expect($loaded[$nodeKey]['contracts'][0]['title'])->toBe('Loadable Contract');
    expect($loaded[$nodeKey]['total'])->toBe(1);
    expect($loaded[$nodeKey]['showing'])->toBe(1);
});

it('filters by status', function () {
    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Draft Contract',
        'workflow_state' => 'draft',
    ]);
    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Executed Contract',
        'workflow_state' => 'executed',
    ]);

    $component = Livewire::test(AgreementTree::class)
        ->set('statusFilter', 'draft');

    $treeData = $component->viewData('treeData');

    $entityNode = collect($treeData)->firstWhere('name', 'Test Entity');
    expect($entityNode)->not->toBeNull();

    // Per-state counts remain unfiltered
    expect($entityNode['draft_count'])->toBe(1);
    expect($entityNode['executed_count'])->toBe(1);
    // Total count is filtered to only 'draft'
    expect($entityNode['total_count'])->toBe(1);
});

it('groups by jurisdiction', function () {
    $jurisdiction = Jurisdiction::create([
        'name' => 'Test Jurisdiction',
        'country_code' => 'TJ',
        'is_active' => true,
    ]);

    EntityJurisdiction::create([
        'entity_id' => $this->entity->id,
        'jurisdiction_id' => $jurisdiction->id,
        'license_number' => 'LIC-001',
        'is_primary' => true,
    ]);

    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Jurisdiction Contract',
        'workflow_state' => 'draft',
    ]);

    $component = Livewire::test(AgreementTree::class)
        ->call('setGroupBy', 'jurisdiction');

    $treeData = $component->viewData('treeData');

    expect($treeData)->toBeArray();
    $jurisdictionNode = collect($treeData)->firstWhere('name', 'Test Jurisdiction');
    expect($jurisdictionNode)->not->toBeNull();
    expect($jurisdictionNode['type'])->toBe('jurisdiction');
    expect($jurisdictionNode['total_count'])->toBe(1);
    expect($jurisdictionNode['children'])->toHaveCount(1);
    expect($jurisdictionNode['children'][0]['name'])->toBe('Test Entity');
});

it('groups by project', function () {
    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Project Contract',
        'workflow_state' => 'draft',
    ]);

    $component = Livewire::test(AgreementTree::class)
        ->call('setGroupBy', 'project');

    $component->assertSet('groupBy', 'project');

    $treeData = $component->viewData('treeData');

    expect($treeData)->toBeArray();
    $projectNode = collect($treeData)->firstWhere('name', 'Test Project');
    expect($projectNode)->not->toBeNull();
    expect($projectNode['type'])->toBe('project');
    expect($projectNode['total_count'])->toBe(1);
    expect($projectNode['entity_name'])->toBe('Test Entity');
});
