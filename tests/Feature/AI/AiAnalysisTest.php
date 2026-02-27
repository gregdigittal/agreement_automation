<?php

use App\Jobs\ProcessAiAnalysis;
use App\Jobs\ProcessRedlineAnalysis;
use App\Models\AiAnalysisResult;
use App\Models\AiExtractedField;
use App\Models\Contract;
use App\Models\RedlineClause;
use App\Models\RedlineSession;
use App\Models\User;
use App\Models\WikiContract;
use App\Services\AiWorkerClient;
use App\Services\RedlineService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->contract = Contract::factory()->create([
        'storage_path' => 'contracts/ai-test.pdf',
        'file_name' => 'ai-test.pdf',
    ]);
    Storage::disk(config('ccrs.contracts_disk'))->put('contracts/ai-test.pdf', 'fake pdf content for AI test');
});

// ── Triggering ──────────────────────────────────────────────────────────

it('1. system_admin can trigger AI analysis', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['summary' => 'Test summary'],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 100, 'output_tokens' => 50],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'summary');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->status)->toBe('completed');
});

it('2. legal role can trigger AI analysis', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['risk_score' => 0.3],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 200, 'output_tokens' => 100],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'risk');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->status)->toBe('completed');
});

it('3. AI Cost Report page restricted to system_admin and finance', function () {
    // system_admin can access
    $this->get('/admin/ai-cost-report-page')->assertSuccessful();

    // finance can access
    $finance = User::factory()->create();
    $finance->assignRole('finance');
    $this->actingAs($finance);
    $this->get('/admin/ai-cost-report-page')->assertSuccessful();

    // other roles cannot
    foreach (['legal', 'commercial', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        $this->get('/admin/ai-cost-report-page')->assertForbidden();
    }
});

it('4. analysis creates AiAnalysisResult record', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['overall_risk_score' => 0.5],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 100, 'output_tokens' => 50],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'extraction');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->contract_id)->toBe($this->contract->id);
    expect($analysis->analysis_type)->toBe('extraction');
});

it('5. analysis status progresses from processing to completed', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['data' => 'processed'],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 100, 'output_tokens' => 50],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'summary');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($analysis->status)->toBe('completed');
});

// ── Five Analysis Types ─────────────────────────────────────────────────

it('6. summary analysis type works', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['executive_summary' => 'This contract is about...'],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 500, 'output_tokens' => 200],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'summary');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($analysis->analysis_type)->toBe('summary');
    expect($analysis->result)->toHaveKey('executive_summary');
});

it('7. extraction analysis creates AiExtractedField records', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => [
                    'fields' => [
                        ['field_name' => 'effective_date', 'field_value' => '2025-01-01', 'confidence' => 0.95],
                        ['field_name' => 'termination_date', 'field_value' => '2026-12-31', 'confidence' => 0.90],
                    ],
                ],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 300, 'output_tokens' => 150],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'extraction');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($analysis->extractedFields)->toHaveCount(2);

    $dateField = AiExtractedField::where('analysis_id', $analysis->id)
        ->where('field_name', 'effective_date')
        ->first();
    expect($dateField)->not->toBeNull();
    expect($dateField->field_value)->toBe('2025-01-01');
});

it('8. risk_assessment analysis type works', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['overall_risk_score' => 0.7, 'risk_factors' => ['high_liability']],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 400, 'output_tokens' => 180],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'risk');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)
        ->where('analysis_type', 'risk')
        ->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->status)->toBe('completed');
    expect($analysis->result)->toHaveKey('overall_risk_score');
});

it('9. deviation analysis type works', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['deviations' => ['Non-standard termination clause']],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 350, 'output_tokens' => 160],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'deviation');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)
        ->where('analysis_type', 'deviation')
        ->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->status)->toBe('completed');
});

it('10. obligations analysis creates obligation records', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => [
                    'obligations' => [
                        [
                            'obligation_type' => 'payment',
                            'description' => 'Monthly payment of $10,000',
                            'due_date' => '2025-06-01',
                            'responsible_party' => 'Buyer',
                            'confidence' => 0.88,
                        ],
                    ],
                ],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 500, 'output_tokens' => 250],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'obligations');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)
        ->where('analysis_type', 'obligations')
        ->first();
    expect($analysis)->not->toBeNull();

    $this->assertDatabaseHas('obligations_register', [
        'contract_id' => $this->contract->id,
        'obligation_type' => 'payment',
    ]);
});

it('11. multiple analysis types can run on same contract', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->twice()
            ->andReturn([
                'result' => ['data' => 'test'],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 100, 'output_tokens' => 50],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'summary');
    ProcessAiAnalysis::dispatchSync($this->contract->id, 'risk');

    $analyses = AiAnalysisResult::where('contract_id', $this->contract->id)->get();
    expect($analyses)->toHaveCount(2);
    expect($analyses->pluck('analysis_type')->toArray())->toContain('summary', 'risk');
});

// ── Results ─────────────────────────────────────────────────────────────

it('12. AiAnalysisResult stores model_used and token usage', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['summary' => 'Short summary'],
                'usage' => [
                    'model_used' => 'claude-3-sonnet',
                    'input_tokens' => 1500,
                    'output_tokens' => 800,
                    'cost_usd' => 0.0125,
                    'processing_time_ms' => 3200,
                ],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'summary');

    $analysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($analysis->model_used)->toBe('claude-3-sonnet');
    expect($analysis->token_usage_input)->toBe(1500);
    expect($analysis->token_usage_output)->toBe(800);
    expect((float) $analysis->cost_usd)->toBe(0.0125);
    expect($analysis->processing_time_ms)->toBe(3200);
});

it('13. failed analysis can be retried', function () {
    // First attempt fails
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andThrow(new \RuntimeException('AI worker unavailable'));
    });

    try {
        ProcessAiAnalysis::dispatchSync($this->contract->id, 'extraction');
    } catch (\RuntimeException $e) {
        // Expected
    }

    $failedAnalysis = AiAnalysisResult::where('contract_id', $this->contract->id)->first();
    expect($failedAnalysis->status)->toBe('failed');
    expect($failedAnalysis->error_message)->toBe('AI worker unavailable');

    // Retry succeeds
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['data' => 'retried'],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 100, 'output_tokens' => 50],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contract->id, 'extraction');

    $analyses = AiAnalysisResult::where('contract_id', $this->contract->id)
        ->orderBy('created_at')
        ->get();
    expect($analyses)->toHaveCount(2);
    expect($analyses->last()->status)->toBe('completed');
});

// ── Cost Tracking ───────────────────────────────────────────────────────

it('14. AI Cost Report accessible to system_admin', function () {
    $this->actingAs($this->admin);
    $this->get('/admin/ai-cost-report-page')->assertSuccessful();
});

it('15. AI Cost Report accessible to finance role', function () {
    $finance = User::factory()->create();
    $finance->assignRole('finance');
    $this->actingAs($finance);

    $this->get('/admin/ai-cost-report-page')->assertSuccessful();
});

it('16. AI Cost Report summary returns cost breakdown', function () {
    // Create some completed analysis records with cost data
    AiAnalysisResult::create([
        'contract_id' => $this->contract->id,
        'analysis_type' => 'summary',
        'status' => 'completed',
        'result' => ['summary' => 'test'],
        'model_used' => 'claude-3',
        'token_usage_input' => 1000,
        'token_usage_output' => 500,
        'cost_usd' => 0.0100,
    ]);

    AiAnalysisResult::create([
        'contract_id' => $this->contract->id,
        'analysis_type' => 'risk',
        'status' => 'completed',
        'result' => ['risk' => 0.5],
        'model_used' => 'claude-3',
        'token_usage_input' => 2000,
        'token_usage_output' => 1000,
        'cost_usd' => 0.0200,
    ]);

    $page = new \App\Filament\Pages\AiCostReportPage();
    $stats = $page->getSummaryStats();

    expect($stats['total_analyses'])->toBe(2);
    expect((float) str_replace(',', '', $stats['total_cost']))->toBeGreaterThan(0);
    expect((int) str_replace(',', '', $stats['total_tokens']))->toBeGreaterThan(0);
});

// ── Redline Review ──────────────────────────────────────────────────────

it('17. start redline session creates record and dispatches job', function () {
    Queue::fake();
    config(['features.redlining' => true]);

    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $template = WikiContract::factory()->published()->create([
        'region_id' => $this->contract->region_id,
        'storage_path' => 'templates/test-template.docx',
    ]);

    $service = app(RedlineService::class);
    $session = $service->startSession($this->contract, $template, $legal);

    $this->assertDatabaseHas('redline_sessions', [
        'id' => $session->id,
        'contract_id' => $this->contract->id,
        'wiki_contract_id' => $template->id,
        'status' => 'pending',
        'created_by' => $legal->id,
    ]);

    Queue::assertPushed(ProcessRedlineAnalysis::class, function ($job) use ($session) {
        return $job->sessionId === $session->id;
    });
});

it('18. redline session requires WikiContract template', function () {
    Queue::fake();
    config(['features.redlining' => true]);

    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    // Delete all templates for the contract's region
    WikiContract::where('region_id', $this->contract->region_id)->delete();

    $service = app(RedlineService::class);

    expect(fn () => $service->startSession($this->contract, null, $legal))
        ->toThrow(\RuntimeException::class, 'No published WikiContract template found');
});

it('19. redline clause review - accept uses suggested text', function () {
    config(['features.redlining' => true]);

    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $session = RedlineSession::factory()->create([
        'contract_id' => $this->contract->id,
        'status' => 'completed',
        'total_clauses' => 1,
        'reviewed_clauses' => 0,
    ]);

    $clause = RedlineClause::factory()->create([
        'session_id' => $session->id,
        'clause_number' => 1,
        'original_text' => 'Original clause.',
        'suggested_text' => 'Suggested clause from template.',
        'change_type' => 'modification',
        'status' => 'pending',
    ]);

    $service = app(RedlineService::class);
    $reviewed = $service->reviewClause($clause, 'accepted', null, $legal);

    expect($reviewed->status)->toBe('accepted');
    expect($reviewed->final_text)->toBe('Suggested clause from template.');
    expect($reviewed->reviewed_by)->toBe($legal->id);
    expect($reviewed->reviewed_at)->not->toBeNull();
});

it('20. redline clause review - user can override with custom text', function () {
    config(['features.redlining' => true]);

    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $session = RedlineSession::factory()->create([
        'contract_id' => $this->contract->id,
        'status' => 'completed',
        'total_clauses' => 1,
        'reviewed_clauses' => 0,
    ]);

    $clause = RedlineClause::factory()->create([
        'session_id' => $session->id,
        'clause_number' => 1,
        'original_text' => 'Original text.',
        'suggested_text' => 'Suggested text.',
        'change_type' => 'modification',
        'status' => 'pending',
    ]);

    $customText = 'My custom modified clause combining both versions.';

    $service = app(RedlineService::class);
    $reviewed = $service->reviewClause($clause, 'modified', $customText, $legal);

    expect($reviewed->status)->toBe('modified');
    expect($reviewed->final_text)->toBe($customText);
});

it('21. redline session completion requires all clauses reviewed', function () {
    config(['features.redlining' => true]);

    $session = RedlineSession::factory()->create([
        'contract_id' => $this->contract->id,
        'status' => 'completed',
        'total_clauses' => 2,
        'reviewed_clauses' => 1,
    ]);

    RedlineClause::factory()->create([
        'session_id' => $session->id,
        'clause_number' => 1,
        'status' => 'accepted',
        'reviewed_at' => now(),
    ]);

    RedlineClause::factory()->create([
        'session_id' => $session->id,
        'clause_number' => 2,
        'status' => 'pending',
        'reviewed_at' => null,
    ]);

    $service = app(RedlineService::class);

    expect(fn () => $service->generateFinalDocument($session))
        ->toThrow(\RuntimeException::class, 'clause(s) have not been reviewed');
});
