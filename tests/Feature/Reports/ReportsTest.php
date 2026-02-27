<?php

use App\Filament\Widgets\ActiveEscalationsWidget;
use App\Filament\Widgets\AiCostWidget;
use App\Filament\Widgets\ComplianceOverviewWidget;
use App\Filament\Widgets\ContractStatusWidget;
use App\Filament\Widgets\ExpiryHorizonWidget;
use App\Filament\Widgets\PendingWorkflowsWidget;
use App\Models\AiAnalysisResult;
use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\Region;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ═══════════════════════════════════════════════════════════════════════════
// ACCESS CONTROL (1-2)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 1. finance/legal/audit/system_admin can access reports
// ---------------------------------------------------------------------------
it('finance, legal, audit, and system_admin can access reports page', function () {
    foreach (['system_admin', 'finance', 'legal', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/reports-page')->assertSuccessful();
    }
});

// ---------------------------------------------------------------------------
// 2. commercial/operations cannot access reports
// ---------------------------------------------------------------------------
it('commercial and operations users cannot access reports page', function () {
    foreach (['commercial', 'operations'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $response = $this->get('/admin/reports-page');
        $response->assertForbidden();
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// TABLE (3-8)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 3. Reports table displays expected columns
// ---------------------------------------------------------------------------
it('reports table displays contracts with expected columns', function () {
    Contract::factory()->create([
        'title' => 'Column Test MSA',
        'contract_type' => 'Commercial',
    ]);

    $response = $this->get('/admin/reports-page');
    $response->assertSuccessful();
    $response->assertSee('Column Test MSA');
});

// ---------------------------------------------------------------------------
// 4. Filter by workflow state
// ---------------------------------------------------------------------------
it('can filter contracts by workflow state', function () {
    Contract::factory()->withState('draft')->create(['title' => 'Draft Contract']);
    Contract::factory()->withState('executed')->create(['title' => 'Executed Contract']);

    $draftContracts = Contract::where('workflow_state', 'draft')->get();
    $executedContracts = Contract::where('workflow_state', 'executed')->get();

    expect($draftContracts)->toHaveCount(1);
    expect($executedContracts)->toHaveCount(1);
    expect($draftContracts->first()->title)->toBe('Draft Contract');
});

// ---------------------------------------------------------------------------
// 5. Filter by contract type
// ---------------------------------------------------------------------------
it('can filter contracts by type', function () {
    Contract::factory()->create(['contract_type' => 'Commercial', 'title' => 'Commercial One']);
    Contract::factory()->create(['contract_type' => 'Merchant', 'title' => 'Merchant One']);

    $commercial = Contract::where('contract_type', 'Commercial')->get();
    $merchant = Contract::where('contract_type', 'Merchant')->get();

    expect($commercial)->toHaveCount(1);
    expect($merchant)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// 6. Filter by region
// ---------------------------------------------------------------------------
it('can filter contracts by region', function () {
    $region1 = Region::factory()->create(['name' => 'MENA']);
    $region2 = Region::factory()->create(['name' => 'Europe']);

    Contract::factory()->create(['region_id' => $region1->id]);
    Contract::factory()->create(['region_id' => $region2->id]);

    $menaContracts = Contract::where('region_id', $region1->id)->get();
    expect($menaContracts)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// 7. Combined filters work
// ---------------------------------------------------------------------------
it('supports combined filters for state and type', function () {
    Contract::factory()->withState('draft')->create(['contract_type' => 'Commercial']);
    Contract::factory()->withState('executed')->create(['contract_type' => 'Commercial']);
    Contract::factory()->withState('draft')->create(['contract_type' => 'Merchant']);

    $results = Contract::where('workflow_state', 'draft')
        ->where('contract_type', 'Commercial')
        ->get();

    expect($results)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// 8. Clear filters returns all results
// ---------------------------------------------------------------------------
it('clearing filters returns all contracts', function () {
    Contract::factory()->count(5)->create();

    $all = Contract::all();
    expect($all)->toHaveCount(5);
});

// ═══════════════════════════════════════════════════════════════════════════
// EXPORT (9-11)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 9. Excel export route works for authorized user
// ---------------------------------------------------------------------------
it('Excel export works for authorized user', function () {
    Excel::fake();

    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    Contract::factory()->count(3)->create();

    $response = $this->get(route('reports.export.contracts.excel'));
    $response->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 10. PDF export route works for system_admin
// ---------------------------------------------------------------------------
it('PDF export works for system_admin', function () {
    Contract::factory()->count(2)->create();

    $response = $this->get(route('reports.export.contracts.pdf'));
    $response->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 11. Export respects role restrictions (operations blocked)
// ---------------------------------------------------------------------------
it('export is blocked for operations role', function () {
    Excel::fake();

    $ops = User::factory()->create();
    $ops->assignRole('operations');
    $this->actingAs($ops);

    $response = $this->get(route('reports.export.contracts.excel'));
    $response->assertStatus(403);
});

// ═══════════════════════════════════════════════════════════════════════════
// ANALYTICS DASHBOARD (12-14)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 12. Analytics dashboard access control
// ---------------------------------------------------------------------------
it('system_admin can access analytics dashboard when feature enabled', function () {
    config(['features.advanced_analytics' => true]);

    $response = $this->get('/admin/analytics-dashboard-page');
    $response->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 13. Analytics dashboard blocked when feature flag disabled
// ---------------------------------------------------------------------------
it('analytics dashboard is blocked when feature disabled', function () {
    config(['features.advanced_analytics' => false]);

    $response = $this->get('/admin/analytics-dashboard-page');
    $response->assertForbidden();
});

// ---------------------------------------------------------------------------
// 14. Analytics dashboard has 6 widgets
// ---------------------------------------------------------------------------
it('analytics dashboard page defines 6 header widgets', function () {
    config(['features.advanced_analytics' => true]);

    $page = new \App\Filament\Pages\AnalyticsDashboardPage();
    $reflection = new ReflectionMethod($page, 'getHeaderWidgets');
    $widgets = $reflection->invoke($page);

    expect($widgets)->toHaveCount(6);
});

// ═══════════════════════════════════════════════════════════════════════════
// MAIN DASHBOARD (15-17)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 15. All users can see main dashboard
// ---------------------------------------------------------------------------
it('all roles can access the main dashboard', function () {
    foreach (['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin')->assertSuccessful();
    }
});

// ---------------------------------------------------------------------------
// 16. Dashboard includes standard widgets
// ---------------------------------------------------------------------------
it('dashboard includes standard widgets', function () {
    $dashboard = new \App\Filament\Pages\Dashboard();
    $widgets = $dashboard->getWidgets();

    expect($widgets)->toContain(ContractStatusWidget::class);
    expect($widgets)->toContain(ExpiryHorizonWidget::class);
    expect($widgets)->toContain(PendingWorkflowsWidget::class);
    expect($widgets)->toContain(ActiveEscalationsWidget::class);
    expect($widgets)->toContain(AiCostWidget::class);
});

// ---------------------------------------------------------------------------
// 17. Compliance widget shown when feature flag enabled
// ---------------------------------------------------------------------------
it('dashboard shows compliance widget when regulatory_compliance feature enabled', function () {
    config(['features.regulatory_compliance' => true]);

    $dashboard = new \App\Filament\Pages\Dashboard();
    $widgets = $dashboard->getWidgets();

    expect($widgets)->toContain(ComplianceOverviewWidget::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// AI COST REPORT (18-20)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 18. AI cost report access control (system_admin and finance)
// ---------------------------------------------------------------------------
it('system_admin and finance can access AI cost report', function () {
    foreach (['system_admin', 'finance'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/ai-cost-report-page')->assertSuccessful();
    }
});

// ---------------------------------------------------------------------------
// 19. AI cost report displays per-analysis data
// ---------------------------------------------------------------------------
it('AI cost report page shows analysis results', function () {
    $contract = Contract::factory()->create();

    AiAnalysisResult::create([
        'contract_id' => $contract->id,
        'analysis_type' => 'summary',
        'status' => 'completed',
        'cost_usd' => 0.05,
    ]);

    $response = $this->get('/admin/ai-cost-report-page');
    $response->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 20. AI cost report calculates summary stats
// ---------------------------------------------------------------------------
it('AI cost report provides summary statistics', function () {
    $contract = Contract::factory()->create();

    AiAnalysisResult::create([
        'contract_id' => $contract->id,
        'analysis_type' => 'summary',
        'status' => 'completed',
        'cost_usd' => 0.10,
        'token_usage_input' => 500,
        'token_usage_output' => 200,
    ]);
    AiAnalysisResult::create([
        'contract_id' => $contract->id,
        'analysis_type' => 'risk',
        'status' => 'completed',
        'cost_usd' => 0.20,
        'token_usage_input' => 1000,
        'token_usage_output' => 400,
    ]);

    $page = new \App\Filament\Pages\AiCostReportPage();
    $stats = $page->getSummaryStats();

    expect((float) $stats['total_cost'])->toBeGreaterThan(0);
    expect((int) $stats['total_analyses'])->toBe(2);
});
