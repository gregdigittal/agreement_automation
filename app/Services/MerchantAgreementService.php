<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\MerchantAgreementInput;

class MerchantAgreementService
{
    public function generate(array $data): Contract
    {
        $contract = Contract::create([
            'region_id' => $data['region_id'],
            'entity_id' => $data['entity_id'],
            'project_id' => $data['project_id'],
            'counterparty_id' => $data['counterparty_id'],
            'contract_type' => 'Merchant',
            'title' => "Merchant Agreement - {$data['vendor_name']}",
            'workflow_state' => 'draft',
            'created_by' => auth()->id(),
        ]);

        MerchantAgreementInput::create([
            'contract_id' => $contract->id,
            'template_id' => $data['template_id'] ?? null,
            'vendor_name' => $data['vendor_name'],
            'merchant_fee' => $data['merchant_fee'] ?? null,
            'region_terms' => $data['region_terms'] ?? null,
            'generated_at' => now(),
            'created_at' => now(),
        ]);

        AuditService::log('merchant_agreement_generated', 'contract', $contract->id, [
            'vendor_name' => $data['vendor_name'],
        ]);

        return $contract;
    }
}
