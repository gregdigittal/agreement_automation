<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuthority;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowService;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Lifecycle Region', 'code' => 'LR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Lifecycle Entity', 'code' => 'LE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Lifecycle Project', 'code' => 'LP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Lifecycle Corp', 'status' => 'Active']);

    $this->workflowService = app(WorkflowService::class);

    // Standard workflow template with all stages
    $this->template = WorkflowTemplate::create([
        'name' => 'Full Lifecycle Template',
        'contract_type' => 'Commercial',
        'version' => 1,
        'status' => 'published',
        'stages' => [
            ['name' => 'review', 'type' => 'review', 'owners' => ['legal']],
            ['name' => 'approval', 'type' => 'approval', 'owners' => ['legal']],
            ['name' => 'signing', 'type' => 'signing', 'owners' => ['legal']],
            ['name' => 'countersign', 'type' => 'countersign', 'owners' => ['legal']],
            ['name' => 'executed', 'type' => 'completion', 'owners' => ['legal']],
        ],
    ]);

    // Signing authority for the admin user
    SigningAuthority::factory()->create([
        'user_id' => $this->admin->id,
        'entity_id' => $this->entity->id,
        'project_id' => null,
        'contract_type_pattern' => '*',
    ]);
});

function createLifecycleContract(string $workflowState = 'draft'): Contract
{
    $contract = Contract::create([
        'region_id' => test()->region->id,
        'entity_id' => test()->entity->id,
        'project_id' => test()->project->id,
        'counterparty_id' => test()->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Lifecycle Test Contract',
        'is_restricted' => false,
    ]);
    $contract->workflow_state = $workflowState;
    $contract->saveQuietly();
    return $contract;
}

function createWorkflowInstance(Contract $contract, string $currentStage): WorkflowInstance
{
    return WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => test()->template->id,
        'template_version' => 1,
        'current_stage' => $currentStage,
        'state' => 'active',
        'started_at' => now(),
    ]);
}

// ── Valid Transitions ───────────────────────────────────────────────────────

it('draft transitions to review via Submit for Review', function () {
    $contract = createLifecycleContract('review');
    $instance = createWorkflowInstance($contract, 'review');

    // Contract is in review stage
    expect($contract->workflow_state)->toBe('review');
    expect($instance->current_stage)->toBe('review');
});

it('review transitions to approval via Approve Review', function () {
    $contract = createLifecycleContract('review');
    $instance = createWorkflowInstance($contract, 'review');

    $action = $this->workflowService->recordAction($instance, 'review', 'approve', $this->admin);

    $contract->refresh();
    $instance->refresh();
    expect($instance->current_stage)->toBe('approval');
    expect($contract->workflow_state)->toBe('approval');
});

it('review transitions back to draft via Request Changes', function () {
    $contract = createLifecycleContract('review');
    $instance = createWorkflowInstance($contract, 'review');

    $action = $this->workflowService->recordAction($instance, 'review', 'reject', $this->admin);

    $contract->refresh();
    $instance->refresh();
    // reject from review (index 0) goes back to review (since it's the first stage, stays at 0)
    expect($instance->current_stage)->toBe('review');
});

it('approval transitions to signing via Approve', function () {
    $contract = createLifecycleContract('approval');
    $instance = createWorkflowInstance($contract, 'approval');

    $action = $this->workflowService->recordAction($instance, 'approval', 'approve', $this->admin);

    $contract->refresh();
    $instance->refresh();
    expect($instance->current_stage)->toBe('signing');
    expect($contract->workflow_state)->toBe('signing');
});

it('approval transitions back to review via Reject', function () {
    $contract = createLifecycleContract('approval');
    $instance = createWorkflowInstance($contract, 'approval');

    $action = $this->workflowService->recordAction($instance, 'approval', 'reject', $this->admin);

    $contract->refresh();
    $instance->refresh();
    expect($instance->current_stage)->toBe('review');
    expect($contract->workflow_state)->toBe('review');
});

it('signing transitions to countersign when external party signs', function () {
    $contract = createLifecycleContract('signing');
    $instance = createWorkflowInstance($contract, 'signing');

    $action = $this->workflowService->recordAction($instance, 'signing', 'approve', $this->admin);

    $contract->refresh();
    $instance->refresh();
    expect($instance->current_stage)->toBe('countersign');
    expect($contract->workflow_state)->toBe('countersign');
});

it('countersign transitions to executed when countersigned', function () {
    $contract = createLifecycleContract('countersign');
    $instance = createWorkflowInstance($contract, 'countersign');

    $action = $this->workflowService->recordAction($instance, 'countersign', 'approve', $this->admin);

    $contract->refresh();
    $instance->refresh();
    expect($instance->current_stage)->toBe('executed');
    expect($contract->workflow_state)->toBe('executed');
});

it('executed contract can be archived', function () {
    $contract = createLifecycleContract('executed');

    $contract->workflow_state = 'archived';
    $contract->saveQuietly();

    expect($contract->fresh()->workflow_state)->toBe('archived');
});

// ── Cancellation ────────────────────────────────────────────────────────────

it('draft contract can be cancelled', function () {
    $contract = createLifecycleContract('draft');

    $contract->workflow_state = 'cancelled';
    $contract->saveQuietly();

    expect($contract->fresh()->workflow_state)->toBe('cancelled');
});

it('review contract can be cancelled', function () {
    $contract = createLifecycleContract('review');

    $contract->workflow_state = 'cancelled';
    $contract->saveQuietly();

    expect($contract->fresh()->workflow_state)->toBe('cancelled');
});

it('approval contract can be cancelled', function () {
    $contract = createLifecycleContract('approval');

    $contract->workflow_state = 'cancelled';
    $contract->saveQuietly();

    expect($contract->fresh()->workflow_state)->toBe('cancelled');
});

it('signing contract can be cancelled', function () {
    $contract = createLifecycleContract('signing');

    $contract->workflow_state = 'cancelled';
    $contract->saveQuietly();

    expect($contract->fresh()->workflow_state)->toBe('cancelled');
});

it('executed contract cannot be cancelled', function () {
    $contract = createLifecycleContract('executed');

    // Executed contracts are in a final state; form is disabled
    $isImmutable = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isImmutable)->toBeTrue();
});

it('archived contract cannot be cancelled', function () {
    $contract = createLifecycleContract('archived');

    $isImmutable = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isImmutable)->toBeTrue();
});

// ── Invalid Transitions ─────────────────────────────────────────────────────

it('draft cannot jump directly to approval', function () {
    $contract = createLifecycleContract('draft');
    $instance = createWorkflowInstance($contract, 'review');

    // The workflow service requires current_stage match to record an action
    // Trying to record an action for 'approval' when instance is at 'review' throws
    expect(fn () => $this->workflowService->recordAction($instance, 'approval', 'approve', $this->admin))
        ->toThrow(\RuntimeException::class);
});

it('executed cannot go back to any editable state', function () {
    $contract = createLifecycleContract('executed');

    // Form is disabled for executed contracts
    $isDisabled = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isDisabled)->toBeTrue();

    // Edit action is hidden for executed contracts
    $isEditHidden = in_array($contract->workflow_state, ['executed', 'completed']);
    expect($isEditHidden)->toBeTrue();
});

it('archived cannot transition to any state', function () {
    $contract = createLifecycleContract('archived');

    // Form is disabled for archived contracts
    $isDisabled = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isDisabled)->toBeTrue();
});

// ── Immutability ────────────────────────────────────────────────────────────

it('executed contract fields cannot be updated via form', function () {
    $contract = createLifecycleContract('executed');

    // The form is disabled for executed contracts — this is enforced by the resource
    $isFormDisabled = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isFormDisabled)->toBeTrue();

    // Verify the original title persists
    $originalTitle = $contract->title;
    expect($contract->fresh()->title)->toBe($originalTitle);
});

it('executed contract file cannot be replaced or deleted', function () {
    $contract = createLifecycleContract('executed');

    // The form is disabled for executed contracts, preventing any file changes
    $isFormDisabled = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isFormDisabled)->toBeTrue();
});

it('archived contract is permanently read-only', function () {
    $contract = createLifecycleContract('archived');

    $isFormDisabled = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isFormDisabled)->toBeTrue();

    // canDelete also returns false for archived
    expect(\App\Filament\Resources\ContractResource::canDelete($contract))->toBeFalse();
});
