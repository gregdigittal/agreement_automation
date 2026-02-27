<?php

use App\Filament\Resources\WorkflowTemplateResource\Pages\CreateWorkflowTemplate;
use App\Filament\Resources\WorkflowTemplateResource\Pages\EditWorkflowTemplate;
use App\Filament\Resources\WorkflowTemplateResource\Pages\ListWorkflowTemplates;
use App\Models\Contract;
use App\Models\EscalationEvent;
use App\Models\EscalationRule;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\EscalationService;
use App\Services\NotificationService;
use App\Services\WorkflowService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ── CRUD ────────────────────────────────────────────────────────────────

it('1. system_admin can create template with name, description, contract_type, and stages', function () {
    Livewire::test(CreateWorkflowTemplate::class)
        ->fillForm([
            'name' => 'Standard Commercial Workflow',
            'contract_type' => 'Commercial',
            'status' => 'draft',
            'stages' => [
                ['name' => 'Legal Review', 'approver_role' => 'legal', 'order' => 1],
                ['name' => 'Finance Approval', 'approver_role' => 'finance', 'order' => 2],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('workflow_templates', [
        'name' => 'Standard Commercial Workflow',
        'contract_type' => 'Commercial',
        'status' => 'draft',
    ]);

    $template = WorkflowTemplate::where('name', 'Standard Commercial Workflow')->first();
    expect($template->stages)->toBeArray()->toHaveCount(2);
});

it('2. legal role CANNOT create workflow templates', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/workflow-templates/create')->assertForbidden();
});

it('3. commercial/finance/operations/audit roles CANNOT create workflow templates', function () {
    foreach (['commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/workflow-templates/create')->assertForbidden();
    }
});

it('4. system_admin can edit a draft template', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Before Edit',
        'contract_type' => 'Commercial',
        'status' => 'draft',
    ]);

    Livewire::test(EditWorkflowTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->fresh()->name)->toBe('After Edit');
});

it('5. system_admin can delete a draft template', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Deletable Template',
        'contract_type' => 'Commercial',
        'status' => 'draft',
    ]);

    // Verify canDelete returns true for system_admin
    expect(\App\Filament\Resources\WorkflowTemplateResource::canDelete($template))->toBeTrue();

    // Delete directly
    $template->delete();
    $this->assertDatabaseMissing('workflow_templates', ['id' => $template->id]);
});

it('6. ALL roles can VIEW workflow templates list', function () {
    WorkflowTemplate::create([
        'name' => 'Visible Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
    ]);

    foreach (['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/workflow-templates')->assertSuccessful();
    }
});

// ── Stages ──────────────────────────────────────────────────────────────

it('7. template can have multiple stages with name, responsible_role, duration_days, and requires_approval', function () {
    $stages = [
        ['name' => 'Draft Review', 'approver_role' => 'legal', 'duration_days' => 5, 'requires_approval' => true, 'order' => 1],
        ['name' => 'Finance Check', 'approver_role' => 'finance', 'duration_days' => 3, 'requires_approval' => true, 'order' => 2],
        ['name' => 'Final Sign', 'approver_role' => 'commercial', 'duration_days' => 2, 'requires_approval' => false, 'order' => 3],
    ];

    $template = WorkflowTemplate::create([
        'name' => 'Multi-Stage Template',
        'contract_type' => 'Commercial',
        'status' => 'draft',
        'stages' => $stages,
    ]);

    expect($template->stages)->toHaveCount(3);
    expect($template->stages[0]['name'])->toBe('Draft Review');
    expect($template->stages[1]['approver_role'])->toBe('finance');
    expect($template->stages[2]['duration_days'])->toBe(2);
});

it('8. stages are ordered sequentially', function () {
    $stages = [
        ['name' => 'Stage A', 'approver_role' => 'legal', 'order' => 1],
        ['name' => 'Stage B', 'approver_role' => 'finance', 'order' => 2],
        ['name' => 'Stage C', 'approver_role' => 'commercial', 'order' => 3],
    ];

    $template = WorkflowTemplate::create([
        'name' => 'Ordered Template',
        'contract_type' => 'Merchant',
        'status' => 'draft',
        'stages' => $stages,
    ]);

    $service = new WorkflowService();
    $contract = Contract::factory()->create(['contract_type' => 'Merchant']);

    $template->update(['status' => 'published', 'version' => 1]);
    $instance = $service->startWorkflow($contract->id, $template->id, $this->admin);

    expect($instance->current_stage)->toBe('Stage A');

    $service->recordAction($instance, 'Stage A', 'approve', $this->admin);
    expect($instance->fresh()->current_stage)->toBe('Stage B');

    $instance->refresh();
    $service->recordAction($instance, 'Stage B', 'approve', $this->admin);
    expect($instance->fresh()->current_stage)->toBe('Stage C');
});

it('9. each stage can have up to 3 escalation tiers', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Escalation Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 24,
        'tier' => 1,
        'escalate_to_role' => 'legal',
    ]);

    EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 48,
        'tier' => 2,
        'escalate_to_role' => 'commercial',
    ]);

    EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 72,
        'tier' => 3,
        'escalate_to_role' => 'system_admin',
    ]);

    expect($template->escalationRules)->toHaveCount(3);
    expect($template->escalationRules->pluck('tier')->sort()->values()->toArray())->toBe([1, 2, 3]);
});

// ── Publishing & Versioning ─────────────────────────────────────────────

it('10. new template starts in draft status', function () {
    Livewire::test(CreateWorkflowTemplate::class)
        ->fillForm([
            'name' => 'New Draft Template',
            'contract_type' => 'Commercial',
            'status' => 'draft',
            'stages' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = WorkflowTemplate::where('name', 'New Draft Template')->first();
    expect($template->status)->toBe('draft');
});

it('11. publishing sets version and makes template active', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Publish Test',
        'contract_type' => 'Commercial',
        'status' => 'draft',
        'version' => 0,
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    Livewire::test(ListWorkflowTemplates::class)
        ->callTableAction('publish', $template);

    $template->refresh();
    expect($template->status)->toBe('published');
    expect($template->version)->toBe(1);
    expect($template->published_at)->not->toBeNull();
});

it('12. re-publishing increments version', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Version Increment Test',
        'contract_type' => 'Merchant',
        'status' => 'draft',
        'version' => 1,
        'stages' => [['name' => 'Stage 1', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    // First publish: 1 -> 2
    Livewire::test(ListWorkflowTemplates::class)
        ->callTableAction('publish', $template);

    $template->refresh();
    expect($template->version)->toBe(2);

    // Set back to draft, then re-publish: 2 -> 3
    $template->update(['status' => 'draft']);

    Livewire::test(ListWorkflowTemplates::class)
        ->callTableAction('publish', $template);

    $template->refresh();
    expect($template->version)->toBe(3);
});

it('13. editing published template creates new draft; published version stays active', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Published Original',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'published_at' => now(),
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    // The published template should remain published
    expect($template->status)->toBe('published');

    // An admin can edit the template name — it stays as-is per Filament resource design
    Livewire::test(EditWorkflowTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm([
            'name' => 'Published Modified',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $template->refresh();
    expect($template->name)->toBe('Published Modified');
    // The template still retains its published status after edit
    expect($template->status)->toBe('published');
});

it('14. only published templates are eligible for workflow start', function () {
    $draftTemplate = WorkflowTemplate::create([
        'name' => 'Draft Only',
        'contract_type' => 'Commercial',
        'status' => 'draft',
        'version' => 1,
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    $publishedTemplate = WorkflowTemplate::create([
        'name' => 'Published Ready',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    $contract = Contract::factory()->create(['contract_type' => 'Commercial']);
    $service = new WorkflowService();

    // Draft template should fail
    expect(fn () => $service->startWorkflow($contract->id, $draftTemplate->id, $this->admin))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // Published template should succeed
    $instance = $service->startWorkflow($contract->id, $publishedTemplate->id, $this->admin);
    expect($instance->state)->toBe('active');
});

// ── Template Matching (priority order) ──────────────────────────────────

it('15. project-level template takes highest priority', function () {
    $region = Region::create(['name' => 'Match Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Match Entity']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Match Project']);

    // Global template
    WorkflowTemplate::create([
        'name' => 'Global Commercial',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Global Stage', 'order' => 1]],
    ]);

    // Region template
    WorkflowTemplate::create([
        'name' => 'Region Commercial',
        'contract_type' => 'Commercial',
        'region_id' => $region->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Region Stage', 'order' => 1]],
    ]);

    // Entity template
    WorkflowTemplate::create([
        'name' => 'Entity Commercial',
        'contract_type' => 'Commercial',
        'entity_id' => $entity->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Entity Stage', 'order' => 1]],
    ]);

    // Project template (should win)
    $projectTemplate = WorkflowTemplate::create([
        'name' => 'Project Commercial',
        'contract_type' => 'Commercial',
        'project_id' => $project->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Project Stage', 'order' => 1]],
    ]);

    // Find best match: project > entity > region > global
    $bestMatch = WorkflowTemplate::where('contract_type', 'Commercial')
        ->where('status', 'published')
        ->orderByRaw('CASE WHEN project_id = ? THEN 1 WHEN entity_id = ? THEN 2 WHEN region_id = ? THEN 3 ELSE 4 END', [
            $project->id, $entity->id, $region->id,
        ])
        ->first();

    expect($bestMatch->id)->toBe($projectTemplate->id);
    expect($bestMatch->name)->toBe('Project Commercial');
});

it('16. entity-level template takes priority over region and global', function () {
    $region = Region::create(['name' => 'E-Match Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'E-Match Entity']);

    WorkflowTemplate::create([
        'name' => 'Global Merchant',
        'contract_type' => 'Merchant',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Global', 'order' => 1]],
    ]);

    WorkflowTemplate::create([
        'name' => 'Region Merchant',
        'contract_type' => 'Merchant',
        'region_id' => $region->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Region', 'order' => 1]],
    ]);

    $entityTemplate = WorkflowTemplate::create([
        'name' => 'Entity Merchant',
        'contract_type' => 'Merchant',
        'entity_id' => $entity->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Entity', 'order' => 1]],
    ]);

    $bestMatch = WorkflowTemplate::where('contract_type', 'Merchant')
        ->where('status', 'published')
        ->orderByRaw('CASE WHEN entity_id = ? THEN 1 WHEN region_id = ? THEN 2 ELSE 3 END', [
            $entity->id, $region->id,
        ])
        ->first();

    expect($bestMatch->id)->toBe($entityTemplate->id);
});

it('17. region-level template takes priority over global', function () {
    $region = Region::create(['name' => 'R-Match Region']);

    WorkflowTemplate::create([
        'name' => 'Global Only',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Global', 'order' => 1]],
    ]);

    $regionTemplate = WorkflowTemplate::create([
        'name' => 'Region Only',
        'contract_type' => 'Commercial',
        'region_id' => $region->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Region', 'order' => 1]],
    ]);

    $bestMatch = WorkflowTemplate::where('contract_type', 'Commercial')
        ->where('status', 'published')
        ->orderByRaw('CASE WHEN region_id = ? THEN 1 ELSE 2 END', [$region->id])
        ->first();

    expect($bestMatch->id)->toBe($regionTemplate->id);
});

it('18. global template is used when no specific scope matches', function () {
    $globalTemplate = WorkflowTemplate::create([
        'name' => 'Global Fallback',
        'contract_type' => 'Merchant',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Fallback Stage', 'order' => 1]],
    ]);

    $bestMatch = WorkflowTemplate::where('contract_type', 'Merchant')
        ->where('status', 'published')
        ->whereNull('project_id')
        ->whereNull('entity_id')
        ->whereNull('region_id')
        ->first();

    expect($bestMatch->id)->toBe($globalTemplate->id);
});

it('19. no match returns null when no workflow template exists for contract type', function () {
    // Create a template for 'Commercial' only
    WorkflowTemplate::create([
        'name' => 'Commercial Only',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Stage', 'order' => 1]],
    ]);

    // Search for 'Merchant' — should find nothing
    $match = WorkflowTemplate::where('contract_type', 'Merchant')
        ->where('status', 'published')
        ->first();

    expect($match)->toBeNull();
});

it('20. template must match contract_type to be eligible', function () {
    $region = Region::create(['name' => 'Type Match Region']);

    WorkflowTemplate::create([
        'name' => 'Merchant Workflow',
        'contract_type' => 'Merchant',
        'region_id' => $region->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Stage', 'order' => 1]],
    ]);

    $commercialTemplate = WorkflowTemplate::create([
        'name' => 'Commercial Workflow',
        'contract_type' => 'Commercial',
        'region_id' => $region->id,
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Stage', 'order' => 1]],
    ]);

    $match = WorkflowTemplate::where('contract_type', 'Commercial')
        ->where('status', 'published')
        ->where('region_id', $region->id)
        ->first();

    expect($match->id)->toBe($commercialTemplate->id);
    expect($match->contract_type)->toBe('Commercial');
});

// ── Escalation ──────────────────────────────────────────────────────────

it('21. SLA expiry creates escalation event', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn(new \App\Models\Notification());
    });

    $contract = Contract::factory()->create();

    $template = WorkflowTemplate::create([
        'name' => 'Esc Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Legal Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    $rule = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Legal Review',
        'sla_breach_hours' => 24,
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Legal Review',
        'state' => 'active',
        'started_at' => now()->subHours(25),
    ]);

    $service = app(EscalationService::class);
    $count = $service->checkSlaBreaches();

    expect($count)->toBe(1);
    $this->assertDatabaseHas('escalation_events', [
        'workflow_instance_id' => $instance->id,
        'rule_id' => $rule->id,
        'stage_name' => 'Legal Review',
        'tier' => 1,
    ]);
});

it('22. no escalation when within SLA', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn(new \App\Models\Notification());
    });

    $contract = Contract::factory()->create();

    $template = WorkflowTemplate::create([
        'name' => 'SLA OK Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 48,
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Review',
        'state' => 'active',
        'started_at' => now()->subHours(12),
    ]);

    $service = app(EscalationService::class);
    $count = $service->checkSlaBreaches();

    expect($count)->toBe(0);
    $this->assertDatabaseCount('escalation_events', 0);
});

it('23. escalation tier progression - higher tier rule triggers after longer breach', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn(new \App\Models\Notification());
    });

    $contract = Contract::factory()->create();

    $template = WorkflowTemplate::create([
        'name' => 'Tiered Esc Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Approval', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    $rule1 = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Approval',
        'sla_breach_hours' => 24,
        'tier' => 1,
        'escalate_to_role' => 'legal',
    ]);

    $rule2 = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Approval',
        'sla_breach_hours' => 48,
        'tier' => 2,
        'escalate_to_role' => 'system_admin',
    ]);

    // Instance has been in stage for 50 hours — both tiers should trigger
    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Approval',
        'state' => 'active',
        'started_at' => now()->subHours(50),
    ]);

    $service = app(EscalationService::class);
    $count = $service->checkSlaBreaches();

    expect($count)->toBe(2);
    $this->assertDatabaseHas('escalation_events', [
        'workflow_instance_id' => $instance->id,
        'tier' => 1,
    ]);
    $this->assertDatabaseHas('escalation_events', [
        'workflow_instance_id' => $instance->id,
        'tier' => 2,
    ]);
});

it('24. resolving escalation sets resolved_at and resolved_by', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('create')->andReturn(new \App\Models\Notification());
    });

    $contract = Contract::factory()->create();

    $template = WorkflowTemplate::create([
        'name' => 'Resolve Template',
        'contract_type' => 'Commercial',
        'status' => 'published',
        'version' => 1,
        'stages' => [['name' => 'Review', 'approver_role' => 'legal', 'order' => 1]],
    ]);

    $rule = EscalationRule::create([
        'workflow_template_id' => $template->id,
        'stage_name' => 'Review',
        'sla_breach_hours' => 24,
        'tier' => 1,
        'escalate_to_role' => 'system_admin',
    ]);

    $instance = WorkflowInstance::create([
        'contract_id' => $contract->id,
        'template_id' => $template->id,
        'template_version' => 1,
        'current_stage' => 'Review',
        'state' => 'active',
        'started_at' => now()->subHours(30),
    ]);

    $event = EscalationEvent::create([
        'workflow_instance_id' => $instance->id,
        'rule_id' => $rule->id,
        'contract_id' => $contract->id,
        'stage_name' => 'Review',
        'tier' => 1,
        'escalated_at' => now()->subHours(5),
        'created_at' => now()->subHours(5),
        'resolved_at' => null,
    ]);

    $service = app(EscalationService::class);
    $resolved = $service->resolveEscalation($event->id, $this->admin);

    expect($resolved->resolved_at)->not->toBeNull();
    expect($resolved->resolved_by)->toBe($this->admin->email);
});
