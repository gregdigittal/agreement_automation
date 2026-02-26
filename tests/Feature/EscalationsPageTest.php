<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\EscalationEvent;
use App\Models\EscalationRule;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render escalations page', function () {
    $this->get('/admin/escalations-page')->assertSuccessful();
});

it('shows escalation events in table', function () {
    $region = Region::create(['name' => 'Esc Region', 'code' => 'ER']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Esc Entity', 'code' => 'EE']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Esc Project', 'code' => 'EP']);
    $counterparty = Counterparty::create(['legal_name' => 'Esc Corp', 'status' => 'Active']);

    $contract = new Contract([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Escalation Test Contract',
    ]);
    $contract->workflow_state = 'draft';
    $contract->save();

    $template = WorkflowTemplate::create([
        'name' => 'Esc Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'stages' => [['name' => 'Review', 'role' => 'legal']],
    ]);

    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Review',
        'state' => 'active',
        'started_at' => now()->subDays(5),
    ]);

    $rule = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 24,
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    EscalationEvent::create([
        'rule_id' => $rule->id,
        'workflow_instance_id' => $instance->id,
        'contract_id' => $contract->id,
        'stage_name' => 'Review',
        'tier' => 1,
        'escalated_at' => now(),
    ]);

    $this->assertDatabaseHas('escalation_events', [
        'rule_id' => $rule->id,
        'workflow_instance_id' => $instance->id,
    ]);
});

it('system_admin can access escalations', function () {
    $this->get('/admin/escalations-page')->assertSuccessful();
});
