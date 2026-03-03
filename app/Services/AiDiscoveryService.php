<?php

namespace App\Services;

use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\GoverningLaw;
use App\Models\Jurisdiction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiDiscoveryService
{
    /**
     * Process AI extraction results and create discovery drafts.
     */
    public function processDiscoveryResults(Contract $contract, string $analysisId, array $discoveries): void
    {
        foreach ($discoveries as $discovery) {
            $type = $discovery['type'] ?? null;
            $data = $discovery['data'] ?? [];
            $confidence = $discovery['confidence'] ?? 0.0;

            if (! $type || empty($data)) {
                continue;
            }

            [$matchedId, $matchedType] = $this->findMatch($type, $data);

            AiDiscoveryDraft::create([
                'contract_id' => $contract->id,
                'analysis_id' => $analysisId,
                'draft_type' => $type,
                'extracted_data' => $data,
                'matched_record_id' => $matchedId,
                'matched_record_type' => $matchedType,
                'confidence' => $confidence,
                'status' => 'pending',
            ]);
        }

        Log::info("AI discovery created " . count($discoveries) . " drafts for contract {$contract->id}");
    }

    private function findMatch(string $type, array $data): array
    {
        return match ($type) {
            'counterparty' => $this->matchCounterparty($data),
            'entity' => $this->matchEntity($data),
            'jurisdiction' => $this->matchJurisdiction($data),
            'governing_law' => $this->matchGoverningLaw($data),
            default => [null, null],
        };
    }

    private function matchCounterparty(array $data): array
    {
        $match = null;
        if (! empty($data['registration_number'])) {
            $match = Counterparty::where('registration_number', $data['registration_number'])->first();
        }
        if (! $match && ! empty($data['legal_name'])) {
            $match = Counterparty::where('legal_name', 'LIKE', '%' . $data['legal_name'] . '%')->first();
        }
        return $match ? [$match->id, Counterparty::class] : [null, null];
    }

    private function matchEntity(array $data): array
    {
        $match = null;
        if (! empty($data['registration_number'])) {
            $match = Entity::where('registration_number', $data['registration_number'])->first();
        }
        if (! $match && ! empty($data['name'])) {
            $match = Entity::where('name', 'LIKE', '%' . $data['name'] . '%')->first();
        }
        return $match ? [$match->id, Entity::class] : [null, null];
    }

    private function matchJurisdiction(array $data): array
    {
        $match = null;
        if (! empty($data['name'])) {
            $match = Jurisdiction::where('name', 'LIKE', '%' . $data['name'] . '%')->first();
        }
        if (! $match && ! empty($data['country_code'])) {
            $match = Jurisdiction::where('country_code', $data['country_code'])->first();
        }
        return $match ? [$match->id, Jurisdiction::class] : [null, null];
    }

    private function matchGoverningLaw(array $data): array
    {
        $match = null;
        if (! empty($data['name'])) {
            $match = GoverningLaw::where('name', 'LIKE', '%' . $data['name'] . '%')->first();
        }
        return $match ? [$match->id, GoverningLaw::class] : [null, null];
    }

    /**
     * Approve a draft — link to matched record or create a new one.
     */
    public function approveDraft(AiDiscoveryDraft $draft, User $actor, ?array $overrides = null): void
    {
        $data = $overrides ?? $draft->extracted_data;

        if ($draft->matched_record_id) {
            $this->linkToContract($draft->contract, $draft->draft_type, $draft->matched_record_id);
        } else {
            $newId = $this->createRecord($draft->draft_type, $data);
            if ($newId) {
                $this->linkToContract($draft->contract, $draft->draft_type, $newId);
            }
        }

        $draft->update([
            'status' => 'approved',
            'extracted_data' => $data,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        AuditService::log('ai_discovery.approved', 'ai_discovery_draft', $draft->id, [
            'draft_type' => $draft->draft_type,
            'contract_id' => $draft->contract_id,
        ], $actor);
    }

    /**
     * Reject a draft.
     */
    public function rejectDraft(AiDiscoveryDraft $draft, User $actor): void
    {
        $draft->update([
            'status' => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    private function linkToContract(Contract $contract, string $type, string $recordId): void
    {
        match ($type) {
            'counterparty' => $contract->update(['counterparty_id' => $recordId]),
            'governing_law' => $contract->update(['governing_law_id' => $recordId]),
            default => null,
        };
    }

    private function createRecord(string $type, array $data): ?string
    {
        return match ($type) {
            'counterparty' => Counterparty::create([
                'legal_name' => $data['legal_name'] ?? 'Unknown',
                'registration_number' => $data['registration_number'] ?? null,
                'jurisdiction' => $data['jurisdiction'] ?? null,
                'registered_address' => $data['registered_address'] ?? null,
                'status' => 'Active',
            ])->id,
            'governing_law' => GoverningLaw::create([
                'name' => $data['name'] ?? 'Unknown',
                'country_code' => $data['country_code'] ?? null,
                'is_active' => true,
            ])->id,
            default => null,
        };
    }
}
