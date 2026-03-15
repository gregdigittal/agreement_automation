<?php

use App\Jobs\CheckSlaBreaches;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\EscalationEvent;
use App\Models\EscalationRule;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\EscalationService;

it('delegates to EscalationService::checkSlaBreaches', function () {
    $mock = Mockery::mock(EscalationService::class);
    $mock->shouldReceive('checkSlaBreaches')->once()->andReturn(3);
    app()->instance(EscalationService::class, $mock);

    $job = new CheckSlaBreaches;
    $job->handle($mock);
});

it('handles zero breaches gracefully', function () {
    $mock = Mockery::mock(EscalationService::class);
    $mock->shouldReceive('checkSlaBreaches')->once()->andReturn(0);
    app()->instance(EscalationService::class, $mock);

    $job = new CheckSlaBreaches;
    $job->handle($mock);
});

it('can be dispatched to the queue', function () {
    Queue::fake();
    CheckSlaBreaches::dispatch();
    Queue::assertPushed(CheckSlaBreaches::class);
});

it('creates escalation events for SLA-breached workflow instances', function () {
    $cp = Counterparty::factory()->create();
    $contract = Contract::factory()->create(['counterparty_id' => $cp->id]);

    $template = WorkflowTemplate::create([
        'name' => 'SLA Test Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'stages' => [['name' => 'Legal Review', 'role' => 'legal', 'order' => 1]],
    ]);

    $rule = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Legal Review',
        'sla_breach_hours' => 1,  // 1 hour — our instance has been active for 48 hours
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Legal Review',
        'state' => 'active',
        'started_at' => now()->subHours(48),
    ]);

    $job = new CheckSlaBreaches;
    $job->handle(app(EscalationService::class));

    expect(EscalationEvent::where('workflow_instance_id', $instance->id)->count())->toBe(1);
});

it('does not create duplicate escalations for the same breach', function () {
    $cp = Counterparty::factory()->create();
    $contract = Contract::factory()->create(['counterparty_id' => $cp->id]);

    $template = WorkflowTemplate::create([
        'name' => 'Dedup Test Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'stages' => [['name' => 'Review', 'role' => 'legal', 'order' => 1]],
    ]);

    $rule = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 1,
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Review',
        'state' => 'active',
        'started_at' => now()->subHours(48),
    ]);

    $service = app(EscalationService::class);
    $job = new CheckSlaBreaches;

    // Run twice — should only create one escalation event
    $job->handle($service);
    $job->handle($service);

    expect(EscalationEvent::where('workflow_instance_id', $instance->id)->count())->toBe(1);
});
