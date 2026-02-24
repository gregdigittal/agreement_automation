<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\EscalationEvent;
use App\Models\EscalationRule;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStageAction;
use App\Models\WorkflowTemplate;
use App\Services\EscalationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EscalationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EscalationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a mock NotificationService so notifyEscalation() does not hit
        // real email/Teams channels during tests.
        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('create')->andReturn(null);
        });

        $this->service = app(EscalationService::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the minimum supporting records needed for every test:
     * a Contract tied to a published WorkflowTemplate.
     *
     * @return array{contract: Contract, template: WorkflowTemplate}
     */
    private function makeContractAndTemplate(): array
    {
        $contract = Contract::factory()->create();

        $template = WorkflowTemplate::create([
            'name'          => 'Test Template',
            'contract_type' => 'Commercial',
            'version'       => 1,
            'status'        => 'published',
            'stages'        => [
                ['name' => 'Legal Review', 'approver_role' => 'legal', 'order' => 1],
            ],
        ]);

        return compact('contract', 'template');
    }

    /**
     * Create an EscalationRule for the given template/stage.
     */
    private function makeRule(WorkflowTemplate $template, string $stageName, int $slaHours = 24, int $tier = 1): EscalationRule
    {
        return EscalationRule::create([
            'workflow_template_id' => $template->id,
            'stage_name'           => $stageName,
            'sla_breach_hours'     => $slaHours,
            'tier'                 => $tier,
            'escalate_to_role'     => 'system_admin',
        ]);
    }

    /**
     * Create a WorkflowInstance in the 'active' state.
     */
    private function makeInstance(Contract $contract, WorkflowTemplate $template, string $currentStage, \DateTimeInterface $startedAt): WorkflowInstance
    {
        return WorkflowInstance::create([
            'contract_id'      => $contract->id,
            'template_id'      => $template->id,
            'template_version' => 1,
            'current_stage'    => $currentStage,
            'state'            => 'active',
            'started_at'       => $startedAt,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * When a workflow instance has been in the current stage longer than the
     * configured SLA hours, checkSlaBreaches() must create an EscalationEvent.
     */
    public function test_check_sla_breaches_creates_escalation_when_overdue(): void
    {
        ['contract' => $contract, 'template' => $template] = $this->makeContractAndTemplate();

        $rule     = $this->makeRule($template, 'Legal Review', slaHours: 24);
        $instance = $this->makeInstance(
            $contract,
            $template,
            'Legal Review',
            now()->subHours(25) // 25 h > 24 h SLA → breach
        );

        $count = $this->service->checkSlaBreaches();

        $this->assertSame(1, $count);

        $this->assertDatabaseHas('escalation_events', [
            'workflow_instance_id' => $instance->id,
            'rule_id'              => $rule->id,
            'contract_id'          => $contract->id,
            'stage_name'           => 'Legal Review',
            'tier'                 => 1,
        ]);
    }

    /**
     * When the instance has been in the stage for fewer hours than the SLA
     * threshold, no escalation event should be created.
     */
    public function test_check_sla_breaches_skips_when_within_sla(): void
    {
        ['contract' => $contract, 'template' => $template] = $this->makeContractAndTemplate();

        $this->makeRule($template, 'Legal Review', slaHours: 48);
        $this->makeInstance(
            $contract,
            $template,
            'Legal Review',
            now()->subHours(12) // 12 h < 48 h SLA → no breach
        );

        $count = $this->service->checkSlaBreaches();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('escalation_events', 0);
    }

    /**
     * When an unresolved EscalationEvent already exists for the same instance
     * and rule, checkSlaBreaches() must not create a duplicate.
     */
    public function test_check_sla_breaches_does_not_duplicate_unresolved(): void
    {
        ['contract' => $contract, 'template' => $template] = $this->makeContractAndTemplate();

        $rule     = $this->makeRule($template, 'Legal Review', slaHours: 24);
        $instance = $this->makeInstance(
            $contract,
            $template,
            'Legal Review',
            now()->subHours(30) // well past SLA
        );

        // Pre-seed an existing unresolved escalation.
        EscalationEvent::create([
            'workflow_instance_id' => $instance->id,
            'rule_id'              => $rule->id,
            'contract_id'          => $contract->id,
            'stage_name'           => 'Legal Review',
            'tier'                 => 1,
            'escalated_at'         => now()->subHours(6),
            'created_at'           => now()->subHours(6),
            'resolved_at'          => null,
        ]);

        $count = $this->service->checkSlaBreaches();

        $this->assertSame(0, $count);
        // Still only the one pre-seeded record — no duplicate.
        $this->assertDatabaseCount('escalation_events', 1);
    }

    /**
     * resolveEscalation() must stamp resolved_at with the current time and
     * set resolved_by to the actor's email address.
     */
    public function test_resolve_escalation_sets_resolved_fields(): void
    {
        ['contract' => $contract, 'template' => $template] = $this->makeContractAndTemplate();

        $rule     = $this->makeRule($template, 'Legal Review', slaHours: 24);
        $instance = $this->makeInstance(
            $contract,
            $template,
            'Legal Review',
            now()->subHours(30)
        );

        $actor = User::factory()->create();
        $actor->assignRole('system_admin');

        $event = EscalationEvent::create([
            'workflow_instance_id' => $instance->id,
            'rule_id'              => $rule->id,
            'contract_id'          => $contract->id,
            'stage_name'           => 'Legal Review',
            'tier'                 => 1,
            'escalated_at'         => now()->subHours(2),
            'created_at'           => now()->subHours(2),
            'resolved_at'          => null,
        ]);

        $resolved = $this->service->resolveEscalation($event->id, $actor);

        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame($actor->email, $resolved->resolved_by);

        $this->assertDatabaseHas('escalation_events', [
            'id'          => $event->id,
            'resolved_by' => $actor->email,
        ]);

        $this->assertNotNull(
            EscalationEvent::find($event->id)->resolved_at,
            'resolved_at should be persisted to the database'
        );
    }

    /**
     * Stage entry time is derived from the most recent StageAction when one
     * exists, rather than from WorkflowInstance::started_at.
     * If the last stage action is within SLA, no escalation should be created
     * even if started_at is very old.
     */
    public function test_check_sla_breaches_uses_last_stage_action_time(): void
    {
        ['contract' => $contract, 'template' => $template] = $this->makeContractAndTemplate();

        $this->makeRule($template, 'Legal Review', slaHours: 24);
        $instance = $this->makeInstance(
            $contract,
            $template,
            'Legal Review',
            now()->subHours(72) // started_at is old but...
        );

        // ...a stage action was recorded recently, so the stage itself is fresh.
        WorkflowStageAction::create([
            'instance_id' => $instance->id,
            'stage_name'  => 'Legal Review',
            'action'      => 'approve',
            'created_at'  => now()->subHours(4), // 4 h < 24 h SLA → no breach
        ]);

        $count = $this->service->checkSlaBreaches();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('escalation_events', 0);
    }
}
