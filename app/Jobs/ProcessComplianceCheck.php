<?php

namespace App\Jobs;

use App\Models\AiAnalysisResult;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use App\Services\AiWorkerClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessComplianceCheck extends TenantAwareJob
{
    public int $tries = 2;

    public int $timeout = 300;

    public array $backoff = [10, 60];

    public function __construct(
        public Contract $contract,
        public RegulatoryFramework $framework,
    ) {}

    public function handle(AiWorkerClient $aiClient): void
    {
        $extractionResult = AiAnalysisResult::where('contract_id', $this->contract->id)
            ->where('analysis_type', 'extraction')
            ->where('status', 'completed')
            ->latest('updated_at')
            ->first();

        $contractText = $extractionResult?->result['text'] ?? null;

        if (empty($contractText)) {
            Log::warning("Compliance check skipped: no completed extraction analysis for contract {$this->contract->id}");

            return;
        }

        $data = $aiClient->checkCompliance($this->contract, $this->framework, $contractText);

        // Re-check replaces previous results — wrapped in transaction for atomicity
        DB::transaction(function () use ($data) {
            ComplianceFinding::where('contract_id', $this->contract->id)
                ->where('framework_id', $this->framework->id)
                ->delete();

            foreach ($data['findings'] ?? [] as $finding) {
                ComplianceFinding::create([
                    'contract_id' => $this->contract->id,
                    'framework_id' => $this->framework->id,
                    'requirement_id' => $finding['requirement_id'],
                    'requirement_text' => $this->getRequirementText($finding['requirement_id']),
                    'status' => $finding['status'] ?? 'unclear',
                    'evidence_clause' => $finding['evidence_clause'] ?? null,
                    'evidence_page' => $finding['evidence_page'] ?? null,
                    'ai_rationale' => $finding['rationale'] ?? null,
                    'confidence' => $finding['confidence'] ?? null,
                ]);
            }
        });

        Log::info("Compliance check completed for contract {$this->contract->id} against framework {$this->framework->framework_name}", [
            'findings_count' => count($data['findings'] ?? []),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessComplianceCheck permanently failed for contract {$this->contract->id}", [
            'framework_id' => $this->framework->id,
            'framework_name' => $this->framework->framework_name,
            'error' => $exception->getMessage(),
        ]);
    }

    private function getRequirementText(string $requirementId): string
    {
        $requirements = $this->framework->requirements ?? [];
        foreach ($requirements as $req) {
            if (($req['id'] ?? '') === $requirementId) {
                return $req['text'] ?? '';
            }
        }

        return '';
    }
}
