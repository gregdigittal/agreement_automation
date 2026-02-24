<?php

use App\Livewire\AgreementTree;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Jurisdiction;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
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
