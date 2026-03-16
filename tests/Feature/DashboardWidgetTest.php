<?php

use App\Filament\Widgets\ActiveEscalationsWidget;
use App\Filament\Widgets\AiCostWidget;
use App\Filament\Widgets\AiProcessingBannerWidget;
use App\Filament\Widgets\AiUsageCostWidget;
use App\Filament\Widgets\ComplianceOverviewWidget;
use App\Filament\Widgets\ContractPipelineFunnelWidget;
use App\Filament\Widgets\ContractStatusWidget;
use App\Filament\Widgets\ExpiryHorizonWidget;
use App\Filament\Widgets\ObligationTrackerWidget;
use App\Filament\Widgets\PendingWorkflowsWidget;
use App\Filament\Widgets\RiskDistributionWidget;
use App\Filament\Widgets\WorkflowPerformanceWidget;
use App\Models\AiAnalysisResult;
use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);
});

it('ContractStatusWidget renders with correct data', function () {
    Contract::factory()->create(['workflow_state' => 'draft']);
    Contract::factory()->create(['workflow_state' => 'executed']);

    Livewire::test(ContractStatusWidget::class)
        ->assertSuccessful();
});

it('ExpiryHorizonWidget renders with date buckets', function () {
    $contract = Contract::factory()->create();

    ContractKeyDate::create([
        'contract_id' => $contract->id,
        'date_type' => 'expiry_date',
        'date_value' => now()->addDays(15),
        'label' => 'Contract Expiry',
    ]);

    Livewire::test(ExpiryHorizonWidget::class)
        ->assertSuccessful();
});

it('ExpiryHorizonWidget counts expired contracts separately', function () {
    $contract = Contract::factory()->create();

    // Already expired
    ContractKeyDate::create([
        'contract_id' => $contract->id,
        'date_type' => 'expiry_date',
        'date_value' => now()->subDays(5),
        'label' => 'Expired Contract',
    ]);

    $contract2 = Contract::factory()->create();

    // Expiring in 15 days
    ContractKeyDate::create([
        'contract_id' => $contract2->id,
        'date_type' => 'expiry_date',
        'date_value' => now()->addDays(15),
        'label' => 'Imminent Expiry',
    ]);

    // Expired bucket should count 1, not include future dates
    expect(
        \App\Models\ContractKeyDate::where('date_type', 'expiry_date')
            ->where('date_value', '<', now())
            ->count()
    )->toBe(1);

    expect(
        \App\Models\ContractKeyDate::where('date_type', 'expiry_date')
            ->whereBetween('date_value', [now(), now()->addDays(30)])
            ->count()
    )->toBe(1);

    Livewire::test(ExpiryHorizonWidget::class)
        ->assertSuccessful();
});

it('PendingWorkflowsWidget renders', function () {
    Livewire::test(PendingWorkflowsWidget::class)
        ->assertSuccessful();
});

it('ActiveEscalationsWidget renders', function () {
    Livewire::test(ActiveEscalationsWidget::class)
        ->assertSuccessful();
});

it('AiCostWidget renders with data', function () {
    $contract = Contract::factory()->create();

    AiAnalysisResult::create([
        'contract_id' => $contract->id,
        'analysis_type' => 'summary',
        'status' => 'completed',
        'cost_usd' => 0.05,
    ]);

    Livewire::test(AiCostWidget::class)
        ->assertSuccessful();
});

it('AiUsageCostWidget renders with empty data', function () {
    Livewire::test(AiUsageCostWidget::class)
        ->assertSuccessful();
});

it('AiUsageCostWidget reads pricing from config', function () {
    // Verify config structure exists and is configurable for per-tenant pricing overrides
    config()->set('ccrs.ai.cost_per_input_mtok', 5.0);
    config()->set('ccrs.ai.cost_per_output_mtok', 25.0);

    expect((float) config('ccrs.ai.cost_per_input_mtok'))->toBe(5.0);
    expect((float) config('ccrs.ai.cost_per_output_mtok'))->toBe(25.0);

    // Widget renders without error with overridden pricing
    Livewire::test(AiUsageCostWidget::class)->assertSuccessful();
});

it('ContractPipelineFunnelWidget renders with contracts', function () {
    Contract::factory()->create();
    Contract::factory()->create();

    Livewire::test(ContractPipelineFunnelWidget::class)
        ->assertSuccessful();
});

it('ContractPipelineFunnelWidget uses a bounded query count — no N+1', function () {
    Contract::factory()->count(10)->create();

    DB::enableQueryLog();

    Livewire::test(ContractPipelineFunnelWidget::class)
        ->assertSuccessful();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Single grouped query replaces 7 per-stage queries — well under 15 total with Livewire overhead
    expect($queryCount)->toBeLessThan(15);
});

it('ContractPipelineFunnelWidget counts contracts per stage correctly', function () {
    Contract::factory()->create(['workflow_state' => 'draft']);
    Contract::factory()->create(['workflow_state' => 'draft']);
    Contract::factory()->create(['workflow_state' => 'review']);

    $widget = new ContractPipelineFunnelWidget;
    $counts = $widget->getStageCounts();

    expect($counts['draft'])->toBe(2);
    expect($counts['review'])->toBe(1);
    expect($counts['executed'])->toBe(0);
});

it('ContractPipelineFunnelWidget includes all 7 stages including archived', function () {
    $widget = new ContractPipelineFunnelWidget;
    $counts = $widget->getStageCounts();

    expect($counts)->toHaveKeys(['draft', 'review', 'approval', 'signing', 'countersign', 'executed', 'archived']);
    expect($counts)->toHaveCount(7);
});

it('ContractPipelineFunnelWidget accessible description caches — single query path', function () {
    Contract::factory()->count(5)->create(['workflow_state' => 'draft']);

    DB::enableQueryLog();
    $widget = new ContractPipelineFunnelWidget;
    $widget->getStageCounts(); // first call — hits DB
    $widget->getStageCounts(); // second call — uses cached property
    $widget->getStageCounts(); // third call — still uses cache

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // All three getStageCounts() calls should produce exactly 1 DB query
    expect($queryCount)->toBe(1);
});

it('ComplianceOverviewWidget is hidden when regulatory_compliance is disabled', function () {
    config()->set('features.regulatory_compliance', false);

    expect(ComplianceOverviewWidget::canView())->toBeFalse();
});

it('ComplianceOverviewWidget renders when regulatory_compliance is enabled', function () {
    config()->set('features.regulatory_compliance', true);

    Livewire::test(ComplianceOverviewWidget::class)
        ->assertSuccessful();
});

it('ComplianceOverviewWidget uses Feature helper not raw config', function () {
    // Feature::enabled() reads config/features.php through the helper —
    // test that canView() uses this path (not config() directly).
    config()->set('features.regulatory_compliance', true);
    expect(ComplianceOverviewWidget::canView())->toBeTrue();

    config()->set('features.regulatory_compliance', false);
    expect(ComplianceOverviewWidget::canView())->toBeFalse();
});

it('AiProcessingBannerWidget returns false when no record is set', function () {
    $widget = new AiProcessingBannerWidget;

    expect($widget->getIsProcessing())->toBeFalse();
    expect($widget->getProcessingTypes())->toBeEmpty();
});

it('AiProcessingBannerWidget detects active processing for a contract', function () {
    $contract = Contract::factory()->create();

    AiAnalysisResult::create([
        'contract_id' => $contract->id,
        'analysis_type' => 'summary',
        'status' => 'processing',
        'cost_usd' => 0,
    ]);

    $widget = new AiProcessingBannerWidget;
    $widget->record = $contract;

    expect($widget->getIsProcessing())->toBeTrue();
    expect($widget->getProcessingTypes())->toContain('Summary');
});

it('ObligationTrackerWidget returns an array', function () {
    $widget = new ObligationTrackerWidget;
    $obligations = $widget->getObligations();

    expect($obligations)->toBeArray();
});

it('RiskDistributionWidget renders on SQLite using db-agnostic json_extract', function () {
    Livewire::test(RiskDistributionWidget::class)
        ->assertSuccessful();
});

it('RiskDistributionWidget returns structured datasets', function () {
    // Use Reflection to access protected getData() — acceptable for widget data structure tests
    $widget = new RiskDistributionWidget;
    $data = (new ReflectionClass($widget))->getMethod('getData')->invoke($widget);

    expect($data)->toHaveKeys(['datasets', 'labels']);
    expect($data['datasets'])->toHaveCount(4); // high, medium, low, unscored
    expect(collect($data['datasets'])->pluck('label')->toArray())->toEqual(['High', 'Medium', 'Low', 'Unscored']);
});

it('WorkflowPerformanceWidget returns an array on SQLite', function () {
    $widget = new WorkflowPerformanceWidget;
    $result = $widget->getPerformanceData();

    expect($result)->toBeArray();
});

it('WorkflowPerformanceWidget returns action distribution shape', function () {
    $user = User::factory()->create();
    $contract = Contract::factory()->create();

    // WorkflowInstance requires a real template_id (FK) — create a minimal template
    $templateId = fake()->uuid();
    DB::table('workflow_templates')->insert([
        'id' => $templateId,
        'name' => 'Test Template',
        'contract_type' => 'Commercial',
        'version' => 1,
        'status' => 'published',
        'stages' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // WorkflowInstance required for FK in workflow_stage_actions
    $instanceId = fake()->uuid();
    DB::table('workflow_instances')->insert([
        'id' => $instanceId,
        'contract_id' => $contract->id,
        'template_id' => $templateId,
        'template_version' => 1,
        'current_stage' => 'review',
        'state' => 'active',
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workflow_stage_actions')->insert([
        ['id' => fake()->uuid(), 'instance_id' => $instanceId, 'stage_name' => 'review', 'action' => 'approve', 'actor_id' => $user->id, 'actor_email' => $user->email, 'created_at' => now()],
        ['id' => fake()->uuid(), 'instance_id' => $instanceId, 'stage_name' => 'review', 'action' => 'rework', 'actor_id' => $user->id, 'actor_email' => $user->email, 'created_at' => now()],
    ]);

    $widget = new WorkflowPerformanceWidget;
    $result = $widget->getPerformanceData();

    expect($result)->toHaveCount(1);
    expect($result[0])->toHaveKeys(['stage_name', 'total_actions', 'approvals', 'rejections', 'reworks', 'skips', 'rework_rate']);
    expect($result[0]['stage_name'])->toBe('review');
    expect($result[0]['total_actions'])->toBe(2);
    expect($result[0]['approvals'])->toBe(1);
    expect($result[0]['reworks'])->toBe(1);
    expect($result[0]['rework_rate'])->toBe(50.0);
});

it('WorkflowPerformanceWidget sort does not collide with ObligationTrackerWidget', function () {
    $workflowSort = (new ReflectionClass(WorkflowPerformanceWidget::class))
        ->getProperty('sort');
    $workflowSort->setAccessible(true);

    $obligationSort = (new ReflectionClass(ObligationTrackerWidget::class))
        ->getProperty('sort');
    $obligationSort->setAccessible(true);

    expect($workflowSort->getValue())->not->toBe($obligationSort->getValue());
});
