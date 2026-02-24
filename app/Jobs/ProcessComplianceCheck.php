<?php

namespace App\Jobs;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessComplianceCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public Contract $contract,
        public RegulatoryFramework $framework,
    ) {}

    public function handle(): void
    {
        $contractText = $this->contract->extracted_text;
        if (empty($contractText)) {
            Log::warning("Compliance check skipped: no extracted text for contract {$this->contract->id}");

            return;
        }

        $payload = [
            'contract_text' => $contractText,
            'contract_id' => $this->contract->id,
            'framework' => [
                'id' => $this->framework->id,
                'name' => $this->framework->framework_name,
                'jurisdiction_code' => $this->framework->jurisdiction_code,
                'requirements' => $this->framework->requirements,
            ],
        ];

        $aiWorkerUrl = config('ccrs.ai_worker_url', 'http://ai-worker:8001');
        $aiWorkerSecret = config('ccrs.ai_worker_secret');

        $response = Http::timeout(280)
            ->withHeaders([
                'X-AI-Worker-Secret' => $aiWorkerSecret,
                'Content-Type' => 'application/json',
            ])
            ->post("{$aiWorkerUrl}/check-compliance", $payload);

        if (! $response->successful()) {
            Log::error("AI worker compliance check failed for contract {$this->contract->id}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("AI worker returned HTTP {$response->status()}");
        }

        $data = $response->json();

        // Re-check replaces previous results — wrapped in transaction for atomicity
        DB::transaction(function () use ($data) {
            ComplianceFinding::where('contract_id', $this->contract->id)
                ->where('framework_id', $this->framework->id)
                ->delete();

            foreach ($data['findings'] ?? [] as $finding) {
                ComplianceFinding::create([
                    'id' => Str::uuid()->toString(),
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
