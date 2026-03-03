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

    /**
     * Auto-apply simple extracted fields (title, contract_type) to a staging contract.
     * These don't require human review — they're direct text values.
     */
    public function autoApplyExtraction(Contract $contract, array $fields): void
    {
        $updates = [];

        foreach ($fields as $field) {
            $name = $field['field_name'] ?? '';
            $value = $field['field_value'] ?? null;

            if (! $value) {
                continue;
            }

            if ($name === 'title' && empty($contract->title)) {
                $updates['title'] = $value;
            }

            if ($name === 'contract_type') {
                $mapped = $this->mapContractType($value);
                if ($mapped) {
                    $updates['contract_type'] = $mapped;
                }
            }
        }

        if (! empty($updates)) {
            $contract->update($updates);
            Log::info('Auto-applied extraction fields to staging contract', [
                'contract_id' => $contract->id,
                'fields' => array_keys($updates),
            ]);
        }
    }

    private function mapContractType(string $raw): ?string
    {
        $lower = strtolower(trim($raw));

        return match (true) {
            str_contains($lower, 'merchant') => 'Merchant',
            str_contains($lower, 'inter-company'), str_contains($lower, 'intercompany'),
            str_contains($lower, 'inter company') => 'Inter-Company',
            str_contains($lower, 'commercial'), str_contains($lower, 'service'),
            str_contains($lower, 'supply'), str_contains($lower, 'license'),
            str_contains($lower, 'nda'), str_contains($lower, 'consulting') => 'Commercial',
            default => null,
        };
    }

    private function linkToContract(Contract $contract, string $type, string $recordId): void
    {
        match ($type) {
            'counterparty' => $contract->update(['counterparty_id' => $recordId]),
            'governing_law' => $contract->update(['governing_law_id' => $recordId]),
            'entity' => $this->linkEntityToContract($contract, $recordId),
            default => null,
        };
    }

    private function linkEntityToContract(Contract $contract, string $entityId): void
    {
        $entity = Entity::find($entityId);
        $updates = ['entity_id' => $entityId];

        // Infer region from the entity's region when the contract doesn't have one yet
        if ($entity && $entity->region_id && ! $contract->region_id) {
            $updates['region_id'] = $entity->region_id;
        }

        $contract->update($updates);
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
