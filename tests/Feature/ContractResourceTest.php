<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);
    $this->region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity', 'code' => 'TE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Test Project', 'code' => 'TP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Test Corp', 'status' => 'Active']);
});

it('can list contracts', function () {
    $this->get('/admin/contracts')->assertSuccessful();
});

it('can create a contract', function () {
    Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Test Contract',
        'workflow_state' => 'draft',
    ]);

    expect(Contract::count())->toBe(1);
    expect(Contract::first()->title)->toBe('Test Contract');
});

it('enforces hierarchical relationships', function () {
    $contract = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Hierarchy Test',
    ]);

    expect($contract->region->id)->toBe($this->region->id);
    expect($contract->entity->region->id)->toBe($this->region->id);
    expect($contract->project->entity->id)->toBe($this->entity->id);
});
