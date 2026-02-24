<?php

namespace App\Services;

use App\Jobs\ProcessComplianceCheck;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * RegulatoryComplianceService — Phase 2: Regulatory compliance checking.
 *
 * This service orchestrates compliance checks by dispatching AI analysis jobs
 * against regulatory frameworks. It does NOT provide legal advice — it flags
 * potential compliance issues for human legal review.
 */
class RegulatoryComplianceService
{
    /**
     * Run a compliance check for a contract against a specific framework.
     * If no framework is specified, auto-detect based on the contract's entity/region jurisdiction.
     */
    public function runComplianceCheck(Contract $contract, ?RegulatoryFramework $framework = null): void
    {
        if (! config('features.regulatory_compliance', false)) {
            throw new \RuntimeException('Regulatory compliance checking is not enabled. Set FEATURE_REGULATORY_COMPLIANCE=true in .env.');
        }

        if ($framework) {
            ProcessComplianceCheck::dispatch($contract, $framework);

            return;
        }

        $frameworks = $this->detectApplicableFrameworks($contract);

        if ($frameworks->isEmpty()) {
            Log::info("No applicable regulatory frameworks found for contract {$contract->id}");

            return;
        }

        foreach ($frameworks as $fw) {
            ProcessComplianceCheck::dispatch($contract, $fw);
        }
    }

    /**
     * Get all compliance findings for a contract, grouped by framework.
     */
    public function getFindings(Contract $contract): Collection
    {
        return ComplianceFinding::where('contract_id', $contract->id)
            ->with('framework')
            ->orderBy('framework_id')
            ->orderBy('requirement_id')
            ->get()
            ->groupBy('framework_id');
    }

    /**
     * Get a compliance score summary for a contract per framework.
     */
    public function getScoreSummary(Contract $contract): Collection
    {
        $findings = ComplianceFinding::where('contract_id', $contract->id)
            ->get()
            ->groupBy('framework_id');

        return $findings->map(function (Collection $group) {
            $total = $group->count();
            $compliant = $group->where('status', 'compliant')->count();
            $nonCompliant = $group->where('status', 'non_compliant')->count();
            $unclear = $group->where('status', 'unclear')->count();
            $notApplicable = $group->where('status', 'not_applicable')->count();

            $scorable = $total - $notApplicable;
            $score = $scorable > 0 ? round(($compliant / $scorable) * 100, 1) : 0.0;

            return [
                'total' => $total,
                'compliant' => $compliant,
                'non_compliant' => $nonCompliant,
                'unclear' => $unclear,
                'not_applicable' => $notApplicable,
                'score' => $score,
            ];
        });
    }

    /**
     * Review a finding — legal user overrides the AI-determined status.
     */
    public function reviewFinding(ComplianceFinding $finding, string $status, User $actor): ComplianceFinding
    {
        $validStatuses = ['compliant', 'non_compliant', 'unclear', 'not_applicable'];
        if (! in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid compliance status: {$status}");
        }

        $finding->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        Log::info("Compliance finding {$finding->id} reviewed", [
            'contract_id' => $finding->contract_id,
            'requirement_id' => $finding->requirement_id,
            'new_status' => $status,
            'reviewer' => $actor->id,
        ]);

        return $finding->fresh();
    }

    /**
     * Auto-detect applicable frameworks based on contract's entity -> region -> jurisdiction mapping.
     */
    private function detectApplicableFrameworks(Contract $contract): Collection
    {
        $contract->loadMissing('entity.region');

        $jurisdictionCode = null;

        if ($contract->entity && $contract->entity->region) {
            $jurisdictionCode = $contract->entity->region->code
                ?? $contract->entity->region->jurisdiction_code
                ?? null;
        }

        $query = RegulatoryFramework::where('is_active', true);

        if ($jurisdictionCode) {
            $query->where(function ($q) use ($jurisdictionCode) {
                $q->where('jurisdiction_code', $jurisdictionCode)
                    ->orWhere('jurisdiction_code', 'GLOBAL');
            });
        } else {
            $query->where('jurisdiction_code', 'GLOBAL');
        }

        return $query->get();
    }
}
