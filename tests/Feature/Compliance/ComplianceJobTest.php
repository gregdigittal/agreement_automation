<?php

use App\Jobs\ProcessComplianceCheck;
use App\Models\AiAnalysisResult;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use App\Services\AiWorkerClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['features.regulatory_compliance' => true]);
});

it('skips compliance check when no extraction result exists', function () {
    $contract = Contract::factory()->create();
    $framework = RegulatoryFramework::factory()->create();

    $aiClient = Mockery::mock(AiWorkerClient::class);
    $aiClient->shouldNotReceive('checkCompliance');
    $this->app->instance(AiWorkerClient::class, $aiClient);

    $job = new ProcessComplianceCheck($contract, $framework);
    $job->handle($aiClient);

    expect(ComplianceFinding::where('contract_id', $contract->id)->count())->toBe(0);
});

it('reads extracted text from ai_analysis_results', function () {
    $contract = Contract::factory()->create();
    $framework = RegulatoryFramework::factory()->create([
        'requirements' => [['id' => 'req-1', 'text' => 'Must include DPA', 'category' => 'data_protection', 'severity' => 'critical']],
    ]);

    AiAnalysisResult::factory()->create([
        'contract_id' => $contract->id,
        'analysis_type' => 'extraction',
        'status' => 'completed',
        'result' => ['text' => 'This is the contract text for compliance checking.'],
    ]);

    $aiClient = Mockery::mock(AiWorkerClient::class);
    $aiClient->shouldReceive('checkCompliance')
        ->once()
        ->andReturn(['findings' => []]);
    $this->app->instance(AiWorkerClient::class, $aiClient);

    $job = new ProcessComplianceCheck($contract, $framework);
    $job->handle($aiClient);
});

it('uses AiWorkerClient not Http directly', function () {
    $contract = Contract::factory()->create();
    $framework = RegulatoryFramework::factory()->create();

    AiAnalysisResult::factory()->create([
        'contract_id' => $contract->id,
        'analysis_type' => 'extraction',
        'status' => 'completed',
        'result' => ['text' => 'contract text'],
    ]);

    Http::fake();

    $aiClient = Mockery::mock(AiWorkerClient::class);
    $aiClient->shouldReceive('checkCompliance')
        ->once()
        ->andReturn(['findings' => []]);
    $this->app->instance(AiWorkerClient::class, $aiClient);

    $job = new ProcessComplianceCheck($contract, $framework);
    $job->handle($aiClient);

    Http::assertNothingSent();
});

it('creates compliance findings from ai worker response', function () {
    $contract = Contract::factory()->create();
    $framework = RegulatoryFramework::factory()->create([
        'requirements' => [
            ['id' => 'req-1', 'text' => 'Must include DPA', 'category' => 'data_protection', 'severity' => 'critical'],
            ['id' => 'req-2', 'text' => 'Consent clause required', 'category' => 'data_protection', 'severity' => 'high'],
        ],
    ]);

    AiAnalysisResult::factory()->create([
        'contract_id' => $contract->id,
        'analysis_type' => 'extraction',
        'status' => 'completed',
        'result' => ['text' => 'contract text'],
    ]);

    $mockResponse = [
        'findings' => [
            [
                'requirement_id' => 'req-1',
                'status' => 'compliant',
                'evidence_clause' => 'Section 5',
                'evidence_page' => 3,
                'rationale' => 'DPA clause found',
                'confidence' => 0.9,
            ],
            [
                'requirement_id' => 'req-2',
                'status' => 'non_compliant',
                'evidence_clause' => null,
                'evidence_page' => null,
                'rationale' => 'No consent clause found',
                'confidence' => 0.85,
            ],
        ],
    ];

    $aiClient = Mockery::mock(AiWorkerClient::class);
    $aiClient->shouldReceive('checkCompliance')->once()->andReturn($mockResponse);
    $this->app->instance(AiWorkerClient::class, $aiClient);

    $job = new ProcessComplianceCheck($contract, $framework);
    $job->handle($aiClient);

    expect(ComplianceFinding::where('contract_id', $contract->id)
        ->where('framework_id', $framework->id)
        ->count()
    )->toBe(2);

    expect(ComplianceFinding::where('contract_id', $contract->id)
        ->where('requirement_id', 'req-1')
        ->where('status', 'compliant')
        ->exists()
    )->toBeTrue();
});

it('replaces previous findings on re-check', function () {
    $contract = Contract::factory()->create();
    $framework = RegulatoryFramework::factory()->create();

    ComplianceFinding::factory()->count(3)->create([
        'contract_id' => $contract->id,
        'framework_id' => $framework->id,
    ]);

    AiAnalysisResult::factory()->create([
        'contract_id' => $contract->id,
        'analysis_type' => 'extraction',
        'status' => 'completed',
        'result' => ['text' => 'contract text'],
    ]);

    $mockResponse = [
        'findings' => [
            ['requirement_id' => 'req-1', 'status' => 'compliant', 'confidence' => 0.9],
            ['requirement_id' => 'req-2', 'status' => 'unclear', 'confidence' => 0.6],
        ],
    ];

    $aiClient = Mockery::mock(AiWorkerClient::class);
    $aiClient->shouldReceive('checkCompliance')->once()->andReturn($mockResponse);
    $this->app->instance(AiWorkerClient::class, $aiClient);

    $job = new ProcessComplianceCheck($contract, $framework);
    $job->handle($aiClient);

    expect(ComplianceFinding::where('contract_id', $contract->id)
        ->where('framework_id', $framework->id)
        ->count()
    )->toBe(2);
});

it('logs failure when job permanently fails', function () {
    $contract = Contract::factory()->create();
    $framework = RegulatoryFramework::factory()->create();

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) use ($contract) {
            return str_contains($message, $contract->id)
                && isset($context['error']);
        });

    $job = new ProcessComplianceCheck($contract, $framework);
    $job->failed(new \RuntimeException('AI worker unreachable'));
});
