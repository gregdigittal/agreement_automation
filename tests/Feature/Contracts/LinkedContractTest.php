<?php

use App\Models\Contract;
use App\Models\ContractLink;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\ContractLinkService;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Link Region', 'code' => 'LK']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Link Entity', 'code' => 'LN']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Link Project', 'code' => 'LJ']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Link Corp', 'status' => 'Active']);

    $this->linkService = app(ContractLinkService::class);
});

function createParentContract(string $workflowState = 'executed'): Contract
{
    $contract = Contract::create([
        'region_id' => test()->region->id,
        'entity_id' => test()->entity->id,
        'project_id' => test()->project->id,
        'counterparty_id' => test()->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Parent Contract',
        'is_restricted' => false,
    ]);
    $contract->workflow_state = $workflowState;
    $contract->saveQuietly();
    return $contract;
}

// ── Creating Linked Contracts ───────────────────────────────────────────────

it('amendment can be created from executed contract', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract(
        parent: $parent,
        linkType: 'amendment',
        title: 'Amendment No. 1',
        actor: $this->admin,
    );

    expect($child)->not->toBeNull();
    expect($child->title)->toBe('Amendment No. 1');
    expect($child->workflow_state)->toBe('draft');
    expect($child->contract_type)->toBe('Commercial');
    expect($child->counterparty_id)->toBe($parent->counterparty_id);

    $this->assertDatabaseHas('contract_links', [
        'parent_contract_id' => $parent->id,
        'child_contract_id' => $child->id,
        'link_type' => 'amendment',
    ]);
});

it('renewal can be created from executed contract', function () {
    $parent = createParentContract('executed');
    $parent->update(['expiry_date' => now()->addYear()]);

    $child = $this->linkService->createLinkedContract(
        parent: $parent,
        linkType: 'renewal',
        title: 'Renewal 2027-2029',
        actor: $this->admin,
        extra: ['renewal_type' => 'new_version'],
    );

    expect($child)->not->toBeNull();
    expect($child->title)->toBe('Renewal 2027-2029');
    expect($child->workflow_state)->toBe('draft');

    $this->assertDatabaseHas('contract_links', [
        'parent_contract_id' => $parent->id,
        'child_contract_id' => $child->id,
        'link_type' => 'renewal',
    ]);
});

it('side letter can be created from executed contract', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract(
        parent: $parent,
        linkType: 'side_letter',
        title: 'Side Letter - Data Sharing',
        actor: $this->admin,
    );

    expect($child)->not->toBeNull();
    expect($child->title)->toBe('Side Letter - Data Sharing');
    expect($child->workflow_state)->toBe('draft');

    $this->assertDatabaseHas('contract_links', [
        'parent_contract_id' => $parent->id,
        'child_contract_id' => $child->id,
        'link_type' => 'side_letter',
    ]);
});

it('linked contract inherits parent region, entity, project, and counterparty', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract(
        parent: $parent,
        linkType: 'amendment',
        title: 'Inherited Fields Amendment',
        actor: $this->admin,
    );

    expect($child->region_id)->toBe($parent->region_id);
    expect($child->entity_id)->toBe($parent->entity_id);
    expect($child->project_id)->toBe($parent->project_id);
    expect($child->counterparty_id)->toBe($parent->counterparty_id);
    expect($child->contract_type)->toBe($parent->contract_type);
});

// ── Relationship Types ──────────────────────────────────────────────────────

it('amendment relationship type is stored correctly', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract($parent, 'amendment', 'Amendment Test', $this->admin);

    $link = ContractLink::where('parent_contract_id', $parent->id)
        ->where('child_contract_id', $child->id)
        ->first();

    expect($link->link_type)->toBe('amendment');
});

it('renewal relationship type is stored correctly', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract($parent, 'renewal', 'Renewal Test', $this->admin, ['renewal_type' => 'new_version']);

    $link = ContractLink::where('parent_contract_id', $parent->id)
        ->where('child_contract_id', $child->id)
        ->first();

    expect($link->link_type)->toBe('renewal');
});

it('side letter relationship type is stored correctly', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract($parent, 'side_letter', 'Side Letter Test', $this->admin);

    $link = ContractLink::where('parent_contract_id', $parent->id)
        ->where('child_contract_id', $child->id)
        ->first();

    expect($link->link_type)->toBe('side_letter');
});

// ── Independent Lifecycles ──────────────────────────────────────────────────

it('amendment has its own independent lifecycle starting in draft', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract($parent, 'amendment', 'Independent Amendment', $this->admin);

    expect($child->workflow_state)->toBe('draft');
    expect($parent->fresh()->workflow_state)->toBe('executed');
});

it('renewal has its own independent lifecycle starting in draft', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract($parent, 'renewal', 'Independent Renewal', $this->admin, ['renewal_type' => 'new_version']);

    expect($child->workflow_state)->toBe('draft');
    expect($parent->fresh()->workflow_state)->toBe('executed');
});

it('side letter has its own independent lifecycle starting in draft', function () {
    $parent = createParentContract('executed');

    $child = $this->linkService->createLinkedContract($parent, 'side_letter', 'Independent Side Letter', $this->admin);

    expect($child->workflow_state)->toBe('draft');
    expect($parent->fresh()->workflow_state)->toBe('executed');
});

// ── Parent Immutability ─────────────────────────────────────────────────────

it('parent contract is not modified by creating an amendment', function () {
    $parent = createParentContract('executed');
    $originalTitle = $parent->title;
    $originalState = $parent->workflow_state;

    $this->linkService->createLinkedContract($parent, 'amendment', 'New Amendment', $this->admin);

    $parent->refresh();
    expect($parent->title)->toBe($originalTitle);
    expect($parent->workflow_state)->toBe($originalState);
});

it('parent contract is not modified by creating a side letter', function () {
    $parent = createParentContract('executed');
    $originalTitle = $parent->title;
    $originalState = $parent->workflow_state;

    $this->linkService->createLinkedContract($parent, 'side_letter', 'New Side Letter', $this->admin);

    $parent->refresh();
    expect($parent->title)->toBe($originalTitle);
    expect($parent->workflow_state)->toBe($originalState);
});

it('renewal extension updates parent expiry date', function () {
    $parent = createParentContract('executed');
    $parent->update(['expiry_date' => now()->addYear()]);

    $newExpiry = now()->addYears(3)->format('Y-m-d');

    $this->linkService->createLinkedContract(
        parent: $parent,
        linkType: 'renewal',
        title: 'Extension Renewal',
        actor: $this->admin,
        extra: ['renewal_type' => 'extension', 'new_expiry_date' => $newExpiry],
    );

    $parent->refresh();
    expect($parent->expiry_date->format('Y-m-d'))->toBe($newExpiry);
});

it('parent shows amendments via relationship', function () {
    $parent = createParentContract('executed');

    $this->linkService->createLinkedContract($parent, 'amendment', 'Amend 1', $this->admin);
    $this->linkService->createLinkedContract($parent, 'amendment', 'Amend 2', $this->admin);

    $parent->refresh();
    expect($parent->amendments)->toHaveCount(2);
});

it('parent shows side letters via relationship', function () {
    $parent = createParentContract('executed');

    $this->linkService->createLinkedContract($parent, 'side_letter', 'Side Letter A', $this->admin);

    $parent->refresh();
    expect($parent->sideLetters)->toHaveCount(1);
});
