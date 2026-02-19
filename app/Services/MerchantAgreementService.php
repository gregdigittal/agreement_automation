<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\MerchantAgreementInput;
use App\Models\WikiContract;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

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

        $input = MerchantAgreementInput::create([
            'contract_id' => $contract->id,
            'template_id' => $data['template_id'] ?? null,
            'vendor_name' => $data['vendor_name'],
            'merchant_fee' => $data['merchant_fee'] ?? null,
            'region_terms' => $data['region_terms'] ?? null,
            'generated_at' => now(),
            'created_at' => now(),
        ]);

        if ($data['template_id'] ?? null) {
            $this->generateDocx($contract, $input, $data);
        }

        AuditService::log('merchant_agreement_generated', 'contract', $contract->id, [
            'vendor_name' => $data['vendor_name'],
            'template_id' => $data['template_id'] ?? null,
        ]);

        return $contract;
    }

    private function generateDocx(Contract $contract, MerchantAgreementInput $input, array $data): void
    {
        $template = WikiContract::find($data['template_id']);
        if (!$template || !$template->storage_path) {
            return;
        }

        $disk = config('ccrs.wiki_contracts_disk', 's3');
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $templatePath = $tempDir . '/template_' . $template->id . '.docx';
        $outputPath = $tempDir . '/ma_' . $contract->id . '.docx';

        try {
            $contents = Storage::disk($disk)->get($template->storage_path);
            file_put_contents($templatePath, $contents);

            $processor = new TemplateProcessor($templatePath);
            $processor->setValue('vendor_name', $data['vendor_name'] ?? '');
            $processor->setValue('merchant_fee', $data['merchant_fee'] ?? '');
            $processor->setValue('contract_date', now()->format('d/m/Y'));
            $processor->setValue('contract_title', $contract->title ?? '');

            $regionTerms = $data['region_terms'] ?? [];
            if (is_array($regionTerms)) {
                foreach ($regionTerms as $key => $value) {
                    $processor->setValue("region_{$key}", (string) $value);
                }
            }

            $processor->saveAs($outputPath);

            $s3Path = "contracts/{$contract->id}/merchant-agreement.docx";
            Storage::disk(config('ccrs.contracts_disk', 's3'))->put($s3Path, file_get_contents($outputPath));

            $contract->update([
                'storage_path' => $s3Path,
                'file_name' => 'merchant-agreement.docx',
                'file_version' => 1,
            ]);
        } finally {
            @unlink($templatePath);
            @unlink($outputPath);
        }
    }
}
