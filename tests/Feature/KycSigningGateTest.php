<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\KycTemplate;
use App\Models\KycTemplateItem;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuthority;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\KycService;
use App\Services\WorkflowService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->region = Region::create(['name' => 'MENA']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'P1']);
    $this->counterparty = Counterparty::create(['legal_name' => 'CP1', 'status' => 'Active']);
    $this->contract = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'KYC Gate Test Contract',
    ]);

    // Signing authority so the signing authority check passes
    SigningAuthority::create([
        'entity_id' => $this->entity->id,
        'project_id' => null,
        'user_id' => $this->user->id,
        'user_email' => $this->user->email,
        'role_or_name' => 'Legal Director',
        'contract_type_pattern' => '*',
    ]);

    $this->workflowTemplate = WorkflowTemplate::create([
        'name' => 'KYC Gate Workflow',
        'contract_type' => 'Commercial',
        'version' => 1,
        'status' => 'published',
        'stages' => [
            ['name' => 'review', 'type' => 'review', 'owners' => ['legal']],
            ['name' => 'signing', 'type' => 'signing', 'owners' => ['legal']],
        ],
    ]);
});

it('blocks signing when KYC pack is incomplete', function () {
    // Create a template with a required item
    $template = KycTemplate::create(['name' => 'Gate Test', 'status' => 'active', 'version' => 1]);
    KycTemplateItem::create([
        'kyc_template_id' => $template->id,
        'sort_order' => 0,
        'label' => 'Trade License',
        'field_type' => 'file_upload',
        'is_required' => true,
    ]);

    // Create the pack (will be incomplete)
    $kycService = new KycService();
    $kycService->createPackForContract($this->contract);
    $this->contract->refresh();

    // Start workflow and advance to signing stage
    $workflowService = new WorkflowService();
    $instance = $workflowService->startWorkflow($this->contract->id, $this->workflowTemplate->id, $this->user);
    $workflowService->recordAction($instance, 'review', 'approve', $this->user);
    $instance->refresh();

    expect($instance->current_stage)->toBe('signing');

    // Attempting to approve at signing stage should throw because KYC is incomplete
    expect(fn () => $workflowService->recordAction($instance, 'signing', 'approve', $this->user))
        ->toThrow(\RuntimeException::class, 'KYC pack incomplete');
});

it('allows signing when KYC pack is complete', function () {
    // Create a template with a required item
    $template = KycTemplate::create(['name' => 'Gate Test', 'status' => 'active', 'version' => 1]);
    KycTemplateItem::create([
        'kyc_template_id' => $template->id,
        'sort_order' => 0,
        'label' => 'Company Name',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    // Create the pack and complete the item
    $kycService = new KycService();
    $pack = $kycService->createPackForContract($this->contract);
    $kycService->completeItem($pack->items->first(), value: 'Digittal FZ-LLC');
    $this->contract->refresh();

    expect($kycService->isReadyForSigning($this->contract))->toBeTrue();

    // Start workflow and advance to signing stage
    $workflowService = new WorkflowService();
    $instance = $workflowService->startWorkflow($this->contract->id, $this->workflowTemplate->id, $this->user);
    $workflowService->recordAction($instance, 'review', 'approve', $this->user);
    $instance->refresh();

    // Signing should succeed
    $action = $workflowService->recordAction($instance, 'signing', 'approve', $this->user);
    expect($action->action)->toBe('approve');
    expect($action->stage_name)->toBe('signing');
});

it('allows signing when no KYC template exists', function () {
    // No KYC template created — gate should pass
    $workflowService = new WorkflowService();
    $instance = $workflowService->startWorkflow($this->contract->id, $this->workflowTemplate->id, $this->user);
    $workflowService->recordAction($instance, 'review', 'approve', $this->user);
    $instance->refresh();

    // Signing should succeed since no KYC pack exists
    $action = $workflowService->recordAction($instance, 'signing', 'approve', $this->user);
    expect($action->action)->toBe('approve');
    expect($action->stage_name)->toBe('signing');
});
