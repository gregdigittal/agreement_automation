<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\MerchantAgreement;
use App\Models\MerchantAgreementInput;
use App\Models\SigningAuthority;
use App\Models\User;
use App\Models\WikiContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * S3 master DOCX template placeholders (PHPWord): ${vendor_name}, ${merchant_fee},
 * ${effective_date}, ${region_terms}, ${entity_name}, ${project_name},
 * ${signing_authority_name}, ${signing_authority_title}.
 */
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

    /**
     * Generate a filled Merchant Agreement DOCX from the master template.
     * Downloads template from S3, fills placeholders, uploads to S3, creates Contract.
     */
    public function generateFromAgreement(MerchantAgreement $agreement, User $actor): Contract
    {
        $counterparty = $agreement->counterparty;
        $signingAuth = SigningAuthority::where('entity_id', $agreement->entity_id)
            ->orderByDesc('created_at')
            ->firstOrFail();

        $templateS3Key = config('ccrs.merchant_agreement_template_s3_key');
        $tempTemplatePath = tempnam(sys_get_temp_dir(), 'ma_template_') . '.docx';

        $templateContent = Storage::disk('s3')->get($templateS3Key);
        if (! $templateContent) {
            throw new \RuntimeException("Master template not found at S3 key: {$templateS3Key}");
        }
        file_put_contents($tempTemplatePath, $templateContent);

        $regionTerms = is_string($agreement->region_terms) ? $agreement->region_terms : (is_array($agreement->region_terms) ? json_encode($agreement->region_terms) : '');

        $values = [
            'vendor_name'             => $counterparty->legal_name,
            'merchant_fee'            => number_format((float) ($agreement->merchant_fee ?? 0), 2),
            'effective_date'          => now()->format('d F Y'),
            'region_terms'            => $regionTerms,
            'entity_name'             => $agreement->entity->name ?? '',
            'project_name'            => $agreement->project->name ?? '',
            'signing_authority_name'  => $signingAuth->role_or_name,
            'signing_authority_title' => $signingAuth->contract_type_pattern ?? '',
        ];

        $processor = new TemplateProcessor($tempTemplatePath);
        foreach ($values as $key => $value) {
            $processor->setValue($key, htmlspecialchars((string) $value));
        }

        $outputTempPath = tempnam(sys_get_temp_dir(), 'ma_output_') . '.docx';
        $processor->saveAs($outputTempPath);

        $s3OutputKey = sprintf(
            'merchant_agreements/%s/%s.docx',
            $counterparty->id,
            now()->format('Ymd_His') . '_' . Str::random(6)
        );
        Storage::disk('s3')->put($s3OutputKey, file_get_contents($outputTempPath));

        @unlink($tempTemplatePath);
        @unlink($outputTempPath);

        $contract = Contract::create([
            'id'              => Str::uuid()->toString(),
            'title'           => 'Merchant Agreement — ' . $counterparty->legal_name,
            'contract_type'   => 'Merchant',
            'counterparty_id' => $counterparty->id,
            'region_id'       => $agreement->region_id,
            'entity_id'       => $agreement->entity_id,
            'project_id'      => $agreement->project_id,
            'workflow_state'  => 'draft',
            'storage_path'    => $s3OutputKey,
            'created_by'      => $actor->id,
        ]);

        AuditService::log(
            action: 'merchant_agreement.generated',
            resourceType: 'contract',
            resourceId: $contract->id,
            details: ['s3_key' => $s3OutputKey, 'counterparty_id' => $counterparty->id],
            actor: $actor,
        );

        return $contract;
    }

}
