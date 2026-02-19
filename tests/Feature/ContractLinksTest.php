<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\ContractLinkService;

beforeEach(function () {
    $this->user = User::create(['id' => 'test-user', 'email' => 'test@example.com', 'name' => 'Test']);
    $this->actingAs($this->user);
    $region = Region::create(['name' => 'R1']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'E1']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'P1']);
    $cp = Counterparty::create(['legal_name' => 'CP1', 'status' => 'Active']);
    $this->parent = Contract::create([
        'region_id' => $region->id, 'entity_id' => $entity->id,
        'project_id' => $project->id, 'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial', 'title' => 'Parent Contract',
    ]);
});

it('creates an amendment linked to parent', function () {
    $child = app(ContractLinkService::class)->createLinkedContract(
        $this->parent, 'amendment', 'Amendment No. 1', $this->user
    );
    expect($child->contract_type)->toBe('Commercial');
    expect($child->counterparty_id)->toBe($this->parent->counterparty_id);
    expect($child->region_id)->toBe($this->parent->region_id);
    $this->assertDatabaseHas('contract_links', [
        'parent_contract_id' => $this->parent->id,
        'child_contract_id' => $child->id,
        'link_type' => 'amendment',
    ]);
});

it('parent shows amendments and side letters', function () {
    app(ContractLinkService::class)->createLinkedContract($this->parent, 'amendment', 'Amend 1', $this->user);
    app(ContractLinkService::class)->createLinkedContract($this->parent, 'side_letter', 'Side Letter A', $this->user);
    $this->parent->refresh();
    expect($this->parent->amendments)->toHaveCount(1);
    expect($this->parent->sideLetters)->toHaveCount(1);
});

it('creates renewal with new version', function () {
    $child = app(ContractLinkService::class)->createLinkedContract(
        $this->parent, 'renewal', 'Renewal 2029', $this->user, ['renewal_type' => 'new_version']
    );
    expect($child->workflow_state)->toBe('draft');
    $this->assertDatabaseHas('contract_links', [
        'parent_contract_id' => $this->parent->id,
        'link_type' => 'renewal',
    ]);
});
