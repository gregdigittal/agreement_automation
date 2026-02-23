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

    public function __construct(
        public readonly string $contractId,
        public readonly string $analysisType,
        public readonly ?string $actorId = null,
        public readonly ?string $actorEmail = null,
    ) {}

    public function handle(AiWorkerClient $client): void
    {
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

        $analysis = AiAnalysisResult::create([
            'id' => Str::uuid()->toString(),
            'contract_id' => $this->contractId,
            'analysis_type' => $this->analysisType,
            'status' => 'processing',
        ]);

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
                'error' => $e->getMessage(),
            ]);
            $analysis->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
        } finally {
            $span?->end();
        }
    }
}
