<?php

use App\Jobs\ProcessAiAnalysis;
use App\Models\AiAnalysisResult;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Services\AiWorkerClient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    $region = Region::factory()->create();
    $entity = Entity::factory()->create(['region_id' => $region->id]);
    $project = Project::factory()->create(['entity_id' => $entity->id]);
    $contract = Contract::factory()->create([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'storage_path' => 'contracts/test.pdf',
        'file_name' => 'test.pdf',
    ]);
    Storage::disk('s3')->put('contracts/test.pdf', 'fake pdf content');
    $this->contractId = $contract->id;
});

it('creates AiAnalysisResult with status completed on success', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'result' => ['overall_risk_score' => 0.5],
                'usage' => ['model_used' => 'claude-3', 'input_tokens' => 100, 'output_tokens' => 50],
            ]);
    });

    ProcessAiAnalysis::dispatchSync($this->contractId, 'extraction');

    $analysis = AiAnalysisResult::where('contract_id', $this->contractId)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->status)->toBe('completed');
    expect($analysis->result)->toBeArray();
});

it('sets status failed and error_message on failure', function () {
    $this->mock(AiWorkerClient::class, function ($mock) {
        $mock->shouldReceive('analyze')
            ->once()
            ->andThrow(new \RuntimeException('AI worker unavailable'));
    });

    ProcessAiAnalysis::dispatchSync($this->contractId, 'extraction');

    $analysis = AiAnalysisResult::where('contract_id', $this->contractId)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->status)->toBe('failed');
    expect($analysis->error_message)->toBe('AI worker unavailable');
});
