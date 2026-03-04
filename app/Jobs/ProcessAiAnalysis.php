<?php

namespace App\Jobs;

use App\Models\AiAnalysisResult;
use App\Models\AiExtractedField;
use App\Models\Contract;
use App\Models\ObligationsRegister;
use App\Services\AiWorkerClient;
use App\Services\AuditService;
use App\Services\TelemetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 2;
    public array $backoff = [10, 60];

    public function __construct(
        public readonly string $contractId,
        public readonly string $analysisType,
        public readonly ?string $actorId = null,
        public readonly ?string $actorEmail = null,
    ) {}

    public function handle(AiWorkerClient $client): void
    {
        Log::info('ProcessAiAnalysis: job STARTED', [
            'contract_id' => $this->contractId,
            'analysis_type' => $this->analysisType,
            'attempt' => $this->attempts(),
            'queue_connection' => config('queue.default'),
        ]);

        $span = TelemetryService::startSpan('job.process_ai_analysis', ['contract_id' => $this->contractId]);
        try {
            $contract = Contract::find($this->contractId);
        if (! $contract) {
            Log::error('ProcessAiAnalysis: contract not found', ['contract_id' => $this->contractId]);
            return;
        }

        if (! $contract->storage_path) {
            Log::error('ProcessAiAnalysis: no storage_path', ['contract_id' => $this->contractId]);
            return;
        }

        $analysis = null;
        try {
            $analysis = AiAnalysisResult::create([
                'id' => Str::uuid()->toString(),
                'contract_id' => $this->contractId,
                'analysis_type' => $this->analysisType,
                'status' => 'processing',
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessAiAnalysis: failed to create analysis record', [
                'contract_id' => $this->contractId,
                'analysis_type' => $this->analysisType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        try {
            $response = $client->analyze(
                contractId: $this->contractId,
                analysisType: $this->analysisType,
                storagePath: $contract->storage_path,
                fileName: $contract->file_name ?? 'contract.pdf',
                context: [
                    'region_id' => $contract->region_id,
                    'entity_id' => $contract->entity_id,
                    'counterparty_id' => $contract->counterparty_id,
                    'existing_entities' => \App\Models\Entity::pluck('name', 'id')->toArray(),
                    'existing_counterparties' => \App\Models\Counterparty::pluck('legal_name', 'id')->take(100)->toArray(),
                ],
            );

            $result = $response['result'] ?? [];
            $usage = $response['usage'] ?? [];

            $analysis->update([
                'status' => 'completed',
                'result' => $result,
                'model_used' => $usage['model_used'] ?? null,
                'token_usage_input' => $usage['input_tokens'] ?? null,
                'token_usage_output' => $usage['output_tokens'] ?? null,
                'cost_usd' => $usage['cost_usd'] ?? null,
                'processing_time_ms' => $usage['processing_time_ms'] ?? null,
                'confidence_score' => $result['overall_risk_score'] ?? $result['confidence'] ?? null,
            ]);

            if ($this->analysisType === 'extraction' && isset($result['fields'])) {
                foreach ($result['fields'] as $field) {
                    AiExtractedField::create([
                        'id' => Str::uuid()->toString(),
                        'contract_id' => $this->contractId,
                        'analysis_id' => $analysis->id,
                        'field_name' => $field['field_name'] ?? '',
                        'field_value' => $field['field_value'] ?? null,
                        'evidence_clause' => $field['evidence_clause'] ?? null,
                        'evidence_page' => $field['evidence_page'] ?? null,
                        'confidence' => $field['confidence'] ?? null,
                    ]);
                }

                // Auto-apply simple extracted fields (title, contract_type) for staging contracts
                if ($contract->workflow_state === 'staging') {
                    app(\App\Services\AiDiscoveryService::class)
                        ->autoApplyExtraction($contract, $result['fields']);
                }
            }

            if ($this->analysisType === 'obligations' && isset($result['obligations'])) {
                foreach ($result['obligations'] as $obl) {
                    ObligationsRegister::create([
                        'id' => Str::uuid()->toString(),
                        'contract_id' => $this->contractId,
                        'analysis_id' => $analysis->id,
                        'obligation_type' => $obl['obligation_type'] ?? 'other',
                        'description' => $obl['description'] ?? '',
                        'due_date' => $obl['due_date'] ?? null,
                        'recurrence' => $obl['recurrence'] ?? null,
                        'responsible_party' => $obl['responsible_party'] ?? null,
                        'evidence_clause' => $obl['evidence_clause'] ?? null,
                        'confidence' => $obl['confidence'] ?? null,
                        'status' => 'active',
                    ]);
                }
            }

            if ($this->analysisType === 'discovery') {
                if (isset($result['discoveries']) && is_array($result['discoveries'])) {
                    $discoveryCount = count($result['discoveries']);
                    Log::info('ProcessAiAnalysis: discovery results received', [
                        'contract_id' => $this->contractId,
                        'discovery_count' => $discoveryCount,
                        'discovery_types' => collect($result['discoveries'])->pluck('type')->all(),
                    ]);

                    if ($discoveryCount === 0) {
                        Log::warning('ProcessAiAnalysis: discovery returned EMPTY discoveries array', [
                            'contract_id' => $this->contractId,
                            'analysis_id' => $analysis->id,
                            'result_preview' => substr(json_encode($result), 0, 500),
                        ]);
                        // Update the analysis with a note so the user knows it wasn't an error
                        $analysis->update([
                            'error_message' => 'AI completed but found zero entities in this document. The document may not contain identifiable counterparties, jurisdictions, or governing law.',
                        ]);
                    } else {
                        $discoveryService = app(\App\Services\AiDiscoveryService::class);
                        $discoveryService->processDiscoveryResults($contract, $analysis->id, $result['discoveries']);
                    }
                } else {
                    Log::warning('ProcessAiAnalysis: discovery completed but no discoveries array in result', [
                        'contract_id' => $this->contractId,
                        'result_keys' => array_keys($result),
                        'result_preview' => substr(json_encode($result), 0, 500),
                    ]);
                    $analysis->update([
                        'error_message' => 'AI response did not contain a discoveries array. Result keys: ' . implode(', ', array_keys($result)),
                    ]);
                }
            }

            AuditService::log(
                action: 'ai_analysis_completed',
                resourceType: 'contract',
                resourceId: $this->contractId,
                details: [
                    'analysis_type' => $this->analysisType,
                    'analysis_id' => $analysis->id,
                    'cost_usd' => $usage['cost_usd'] ?? null,
                ],
            );

            Log::info('ProcessAiAnalysis completed', [
                'contract_id' => $this->contractId,
                'analysis_type' => $this->analysisType,
                'cost_usd' => $usage['cost_usd'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessAiAnalysis failed', [
                'contract_id' => $this->contractId,
                'analysis_type' => $this->analysisType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $analysis?->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
        } finally {
            $span?->end();
        }
    }
}
