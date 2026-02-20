<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\MerchantAgreementInput;
use App\Models\User;
use App\Models\WikiContract;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class MerchantAgreementService
{
    public function generate(Contract $contract, array $inputs, User $actor): array
    {
        $templateId = $inputs['template_id'] ?? null;
        $wikiTemplate = $templateId ? WikiContract::find($templateId) : null;

        if ($wikiTemplate?->storage_path) {
            $fileService = app(ContractFileService::class);
            $contents = $fileService->download($wikiTemplate->storage_path);
            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }
            $templatePath = $tempDir . '/tpl_' . $wikiTemplate->id . '.docx';
            $outputPath = $tempDir . '/ma_' . $contract->id . '.docx';
            file_put_contents($templatePath, $contents);

            try {
                $processor = new TemplateProcessor($templatePath);
                $processor->setValue('vendor_name', $inputs['vendor_name'] ?? '');
                $processor->setValue('merchant_fee', $inputs['merchant_fee'] ?? '');
                $processor->setValue('contract_date', now()->format('d/m/Y'));
                $processor->setValue('contract_title', $contract->title ?? '');
                $regionTerms = $inputs['region_terms'] ?? [];
                if (is_array($regionTerms)) {
                    foreach ($regionTerms as $key => $value) {
                        $processor->setValue("region_{$key}", (string) $value);
                    }
                }
                $processor->saveAs($outputPath);

                $disk = config('ccrs.contracts_disk', 's3');
                $s3Path = "contracts/{$contract->id}/merchant-agreement-" . now()->format('YmdHis') . ".docx";
                Storage::disk($disk)->put($s3Path, file_get_contents($outputPath));

                $contract->update([
                    'storage_path' => $s3Path,
                    'file_name' => basename($s3Path),
                    'file_version' => ($contract->file_version ?? 0) + 1,
                ]);
            } finally {
                @unlink($templatePath);
                @unlink($outputPath);
            }
        }

        $generatedAt = now();
        MerchantAgreementInput::create([
            'contract_id' => $contract->id,
            'template_id' => $templateId,
            'vendor_name' => $inputs['vendor_name'] ?? '',
            'merchant_fee' => $inputs['merchant_fee'] ?? null,
            'region_terms' => $inputs['region_terms'] ?? null,
            'generated_at' => $generatedAt,
            'created_at' => $generatedAt,
        ]);

        AuditService::log('merchant_agreement_generated', 'contract', $contract->id, [
            'vendor_name' => $inputs['vendor_name'] ?? null,
        ], $actor);

        return [
            'contract_id' => $contract->id,
            'generated_at' => $generatedAt->toIso8601String(),
        ];
    }
}
