<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContractLinkService
{
    public function createLinkedContract(
        Contract $parent,
        string $linkType,
        string $title,
        User $actor,
        array $extra = [],
    ): Contract {
        return DB::transaction(function () use ($parent, $linkType, $title, $actor, $extra) {
            if ($linkType === 'renewal' && ($extra['renewal_type'] ?? null) === 'extension') {
                if (!empty($extra['new_expiry_date'])) {
                    $parent->update(['expiry_date' => $extra['new_expiry_date']]);
                }
                $title .= ' (Extension)';
            }

            $child = Contract::create([
                'id' => Str::uuid()->toString(),
                'title' => $title,
                'contract_type' => $parent->contract_type,
                'counterparty_id' => $parent->counterparty_id,
                'region_id' => $parent->region_id,
                'entity_id' => $parent->entity_id,
                'project_id' => $parent->project_id,
                'workflow_state' => 'draft',
                'storage_path' => $extra['storage_path'] ?? null,
                'created_by' => $actor->id,
            ]);

            ContractLink::create([
                'id' => Str::uuid()->toString(),
                'parent_contract_id' => $parent->id,
                'child_contract_id' => $child->id,
                'link_type' => $linkType,
            ]);

            AuditService::log(
                "contract.{$linkType}_created",
                'contract',
                $child->id,
                ['parent_contract_id' => $parent->id, 'link_type' => $linkType],
            );

            return $child;
        });
    }
}
