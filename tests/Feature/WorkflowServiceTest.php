<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\WorkflowService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $region = Region::create(['name' => 'R1']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'E1']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'P1']);
    $cp = Counterparty::create(['legal_name' => 'CP1', 'status' => 'Active']);
    $this->contract = Contract::create([
        'region_id' => $region->id, 'entity_id' => $entity->id,
        'project_id' => $project->id, 'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial', 'title' => 'WF Test',
    ]);
    $this->template = WorkflowTemplate::create([
        'name' => 'Standard', 'contract_type' => 'Commercial', 'version' => 1,
        'status' => 'published',
        'stages' => [
            ['name' => 'Review', 'approver_role' => 'legal', 'order' => 1],
            ['name' => 'Sign', 'approver_role' => 'legal', 'order' => 2],
        ],
    ]);
});

it('starts a workflow', function () {
    $service = new WorkflowService();
    $instance = $service->startWorkflow($this->contract->id, $this->template->id, $this->user);
    expect($instance->state)->toBe('active');
    expect($instance->current_stage)->toBe('Review');
    expect($this->contract->fresh()->workflow_state)->toBe('Review');
});

it('prevents duplicate active workflows', function () {
    $service = new WorkflowService();
    $service->startWorkflow($this->contract->id, $this->template->id, $this->user);
    expect(fn () => $service->startWorkflow($this->contract->id, $this->template->id, $this->user))
        ->toThrow(\RuntimeException::class);
});

it('advances workflow on approve', function () {
    $service = new WorkflowService();
    $instance = $service->startWorkflow($this->contract->id, $this->template->id, $this->user);
    $service->recordAction($instance, 'Review', 'approve', $this->user);
    expect($instance->fresh()->current_stage)->toBe('Sign');
});

it('completes workflow at last stage', function () {
    $service = new WorkflowService();
    $instance = $service->startWorkflow($this->contract->id, $this->template->id, $this->user);
    $service->recordAction($instance, 'Review', 'approve', $this->user);
    $instance->refresh();
    $service->recordAction($instance, 'Sign', 'approve', $this->user);
    $instance->refresh();
    expect($instance->state)->toBe('completed');
    expect($this->contract->fresh()->workflow_state)->toBe('completed');
});

it('rejects back to previous stage', function () {
    $service = new WorkflowService();
    $instance = $service->startWorkflow($this->contract->id, $this->template->id, $this->user);
    $service->recordAction($instance, 'Review', 'approve', $this->user);
    $instance->refresh();
    $service->recordAction($instance, 'Sign', 'reject', $this->user);
    expect($instance->fresh()->current_stage)->toBe('Review');
});
