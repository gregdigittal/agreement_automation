<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EntityJurisdiction;
use App\Models\KycPack;
use App\Models\KycPackItem;
use App\Models\KycTemplate;
use Illuminate\Support\Collection;

class KycService
{
    /**
     * Find the most specific active KYC template for a contract.
     *
     * Priority scoring:
     *   entity_id match   = +4
     *   jurisdiction_id match = +2
     *   contract_type_pattern match = +1
     *
     * NULL template fields match anything (wildcard).
     */
    public function findMatchingTemplate(Contract $contract): ?KycTemplate
    {
        // Get the entity's primary jurisdiction
        $primaryJurisdiction = EntityJurisdiction::where('entity_id', $contract->entity_id)
            ->where('is_primary', true)
            ->first();

        $jurisdictionId = $primaryJurisdiction?->jurisdiction_id;

        $templates = KycTemplate::where('status', 'active')
            ->where(function ($query) use ($contract) {
                $query->whereNull('entity_id')
                    ->orWhere('entity_id', $contract->entity_id);
            })
            ->where(function ($query) use ($jurisdictionId) {
                $query->whereNull('jurisdiction_id');
                if ($jurisdictionId) {
                    $query->orWhere('jurisdiction_id', $jurisdictionId);
                }
            })
            ->where(function ($query) use ($contract) {
                $query->whereNull('contract_type_pattern')
                    ->orWhere('contract_type_pattern', '*')
                    ->orWhere('contract_type_pattern', $contract->contract_type);
            })
            ->get();

        if ($templates->isEmpty()) {
            return null;
        }

        // Score each template by specificity
        return $templates->sortByDesc(function (KycTemplate $template) use ($contract, $jurisdictionId) {
            $score = 0;

            if ($template->entity_id !== null && $template->entity_id === $contract->entity_id) {
                $score += 4;
            }

            if ($template->jurisdiction_id !== null && $jurisdictionId && $template->jurisdiction_id === $jurisdictionId) {
                $score += 2;
            }

            if ($template->contract_type_pattern !== null
                && $template->contract_type_pattern !== '*'
                && $template->contract_type_pattern === $contract->contract_type) {
                $score += 1;
            }

            return $score;
        })->first();
    }

    /**
     * Create an immutable KYC pack for a contract from the best-matching template.
     * If a pack already exists, return the existing one.
     */
    public function createPackForContract(Contract $contract): ?KycPack
    {
        // Return existing pack if one already exists
        $existingPack = $contract->kycPack;
        if ($existingPack) {
            return $existingPack;
        }

        $template = $this->findMatchingTemplate($contract);
        if (!$template) {
            return null;
        }

        $pack = KycPack::create([
            'contract_id' => $contract->id,
            'kyc_template_id' => $template->id,
            'template_version' => $template->version ?? 1,
            'status' => 'incomplete',
        ]);

        // Copy all template items as immutable pack items (snapshot)
        foreach ($template->items as $templateItem) {
            KycPackItem::create([
                'kyc_pack_id' => $pack->id,
                'kyc_template_item_id' => $templateItem->id,
                'sort_order' => $templateItem->sort_order,
                'label' => $templateItem->label,
                'description' => $templateItem->description,
                'field_type' => $templateItem->field_type,
                'is_required' => $templateItem->is_required,
                'options' => $templateItem->options,
                'validation_rules' => $templateItem->validation_rules,
                'status' => 'pending',
            ]);
        }

        // Reload items relationship
        $pack->load('items');

        AuditService::log(
            'kyc_pack.created',
            'kyc_pack',
            $pack->id,
            [
                'contract_id' => $contract->id,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'items_count' => $pack->items->count(),
            ]
        );

        return $pack;
    }

    /**
     * Complete a KYC pack item.
     *
     * For file_upload: sets file_path.
     * For attestation: sets attested_by/attested_at.
     * For others: sets value.
     *
     * After completion, checks if all required items are done and marks pack complete.
     */
    public function completeItem(KycPackItem $item, ?string $value = null, ?string $filePath = null): void
    {
        $updateData = [
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ];

        switch ($item->field_type) {
            case 'file_upload':
                $updateData['file_path'] = $filePath;
                break;
            case 'attestation':
                $updateData['attested_by'] = auth()->id();
                $updateData['attested_at'] = now();
                break;
            default:
                $updateData['value'] = $value;
                break;
        }

        $item->update($updateData);

        // Check if all required items are completed — mark pack as complete
        $pack = $item->pack;
        if ($pack->isComplete()) {
            $pack->update([
                'status' => 'complete',
                'completed_at' => now(),
            ]);

            AuditService::log(
                'kyc_pack.completed',
                'kyc_pack',
                $pack->id,
                ['contract_id' => $pack->contract_id]
            );
        }
    }

    /**
     * Check if the contract is ready for signing from a KYC perspective.
     * Returns true if no pack exists OR pack status is 'complete'.
     */
    public function isReadyForSigning(Contract $contract): bool
    {
        $pack = $contract->kycPack;

        if (!$pack) {
            return true;
        }

        return $pack->status === 'complete';
    }

    /**
     * Get required pending items for the contract's KYC pack.
     */
    public function getMissingItems(Contract $contract): Collection
    {
        $pack = $contract->kycPack;

        if (!$pack) {
            return collect();
        }

        return $pack->items()
            ->where('is_required', true)
            ->where('status', 'pending')
            ->get();
    }
}
