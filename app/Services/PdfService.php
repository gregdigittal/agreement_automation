<?php

namespace App\Services;

use App\Models\SigningSession;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    public function computeHash(string $pdfContent): string
    {
        return hash('sha256', $pdfContent);
    }

    public function getPageCount(string $storagePath): int
    {
        $content = Storage::disk(config('ccrs.contracts_disk'))->get($storagePath);
        $pdf = new \setasign\Fpdi\Fpdi();
        $tmpFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmpFile, $content);
        $count = $pdf->setSourceFile($tmpFile);
        @unlink($tmpFile);
        return $count;
    }

    public function overlaySignatures(string $storagePath, array $signatures): string
    {
        $content = Storage::disk(config('ccrs.contracts_disk'))->get($storagePath);
        $tmpSource = tempnam(sys_get_temp_dir(), 'pdf_src_');
        file_put_contents($tmpSource, $content);

        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($tmpSource);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            // Overlay signatures for this page
            foreach ($signatures as $sig) {
                if (($sig['page'] ?? 0) === $i && !empty($sig['image_path'])) {
                    $imgContent = Storage::disk(config('ccrs.contracts_disk'))->get($sig['image_path']);
                    $tmpImg = tempnam(sys_get_temp_dir(), 'sig_');
                    file_put_contents($tmpImg, $imgContent);
                    $pdf->Image($tmpImg, $sig['x'], $sig['y'], $sig['width'], $sig['height']);
                    @unlink($tmpImg);
                }
            }
        }

        $outputPath = 'contracts/signed/' . \Illuminate\Support\Str::uuid() . '.pdf';
        $tmpOutput = tempnam(sys_get_temp_dir(), 'pdf_out_');
        $pdf->Output($tmpOutput, 'F');
        Storage::disk(config('ccrs.contracts_disk'))->put($outputPath, file_get_contents($tmpOutput));
        @unlink($tmpOutput);
        @unlink($tmpSource);

        return $outputPath;
    }

    public function generateAuditCertificate(SigningSession $session): string
    {
        $session->load(['signers', 'auditLog', 'contract']);

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('CCRS');
        $pdf->SetTitle('Signing Audit Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Signing Audit Certificate', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Contract: ' . ($session->contract->title ?? $session->contract_id), 0, 1);
        $pdf->Cell(0, 6, 'Session ID: ' . $session->id, 0, 1);
        $pdf->Cell(0, 6, 'Document Hash: ' . ($session->document_hash ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 6, 'Completed: ' . ($session->completed_at?->toDateTimeString() ?? 'N/A'), 0, 1);
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Signers', 0, 1);
        $pdf->SetFont('helvetica', '', 9);

        foreach ($session->signers as $signer) {
            $pdf->Cell(0, 5, sprintf(
                '%s (%s) - %s at %s | IP: %s',
                $signer->signer_name,
                $signer->signer_email,
                $signer->status,
                $signer->signed_at?->toDateTimeString() ?? 'N/A',
                $signer->ip_address ?? 'N/A'
            ), 0, 1);
        }

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Audit Trail', 0, 1);
        $pdf->SetFont('helvetica', '', 8);

        foreach ($session->auditLog as $entry) {
            $pdf->Cell(0, 4, sprintf(
                '[%s] %s | IP: %s',
                $entry->created_at->toDateTimeString(),
                $entry->event,
                $entry->ip_address ?? 'system'
            ), 0, 1);
        }

        $outputPath = 'contracts/audit/' . $session->id . '_certificate.pdf';
        $tmpOutput = tempnam(sys_get_temp_dir(), 'cert_');
        $pdf->Output($tmpOutput, 'F');
        Storage::disk(config('ccrs.contracts_disk'))->put($outputPath, file_get_contents($tmpOutput));
        @unlink($tmpOutput);

        return $outputPath;
    }
}
