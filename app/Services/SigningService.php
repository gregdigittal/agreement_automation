<?php

namespace App\Services;

use App\Mail\SigningComplete;
use App\Mail\SigningInvitation;
use App\Models\Contract;
use App\Models\SigningAuditLog;
use App\Models\SigningField;
use App\Models\SigningSession;
use App\Models\SigningSessionSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SigningService
{
    /**
     * Create a new signing session for a contract.
     */
    public function createSession(Contract $contract, array $signers, string $order = 'sequential'): SigningSession
    {
        return DB::transaction(function () use ($contract, $signers, $order) {
            // Compute SHA-256 hash of the contract's PDF
            $fileService = app(ContractFileService::class);
            $fileContents = $fileService->download($contract->storage_path);
            $documentHash = hash('sha256', $fileContents);

            $session = SigningSession::create([
                'contract_id' => $contract->id,
                'initiated_by' => auth()->id(),
                'signing_order' => $order,
                'status' => 'active',
                'document_hash' => $documentHash,
                'expires_at' => now()->addDays(30),
            ]);

            foreach ($signers as $index => $signerData) {
                SigningSessionSigner::create([
                    'signing_session_id' => $session->id,
                    'signer_name' => $signerData['name'],
                    'signer_email' => $signerData['email'],
                    'signer_type' => $signerData['type'] ?? 'external',
                    'signing_order' => $signerData['order'] ?? ($index + 1),
                    'status' => 'pending',
                ]);
            }

            SigningAuditLog::create([
                'signing_session_id' => $session->id,
                'event' => 'created',
                'details' => [
                    'signing_order' => $order,
                    'signer_count' => count($signers),
                    'initiated_by' => auth()->user()?->name,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $session;
        });
    }

    /**
     * Send the signing invitation to a signer.
     */
    public function sendToSigner(SigningSessionSigner $signer): void
    {
        $token = Str::random(64);

        $signer->update([
            'token' => $token,
            'token_expires_at' => now()->addDays(7),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Mail::to($signer->signer_email)->send(new SigningInvitation($signer));

        SigningAuditLog::create([
            'signing_session_id' => $signer->signing_session_id,
            'signer_id' => $signer->id,
            'event' => 'sent',
            'details' => [
                'signer_name' => $signer->signer_name,
                'signer_email' => $signer->signer_email,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Validate a signing token and return the signer.
     *
     * @throws \RuntimeException
     */
    public function validateToken(string $token): SigningSessionSigner
    {
        $signer = SigningSessionSigner::where('token', $token)->first();

        if (!$signer) {
            throw new \RuntimeException('Invalid signing token.');
        }

        if ($signer->token_expires_at && $signer->token_expires_at->isPast()) {
            throw new \RuntimeException('This signing link has expired.');
        }

        $session = $signer->session;
        if (!$session || $session->status !== 'active') {
            throw new \RuntimeException('This signing session is no longer active.');
        }

        // Record first view
        if (!$signer->viewed_at) {
            $signer->update(['viewed_at' => now()]);

            SigningAuditLog::create([
                'signing_session_id' => $signer->signing_session_id,
                'signer_id' => $signer->id,
                'event' => 'viewed',
                'details' => [
                    'signer_name' => $signer->signer_name,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        }

        return $signer;
    }

    /**
     * Capture a signer's signature and field values.
     */
    public function captureSignature(SigningSessionSigner $signer, array $fieldValues, string $signatureImageBase64): void
    {
        // Decode and store signature image to S3
        $imageData = base64_decode($signatureImageBase64);
        $path = "signing/{$signer->signing_session_id}/{$signer->id}.png";
        $disk = config('ccrs.contracts_disk', 's3');
        Storage::disk($disk)->put($path, $imageData);

        $signer->update([
            'signature_image_path' => $path,
            'signature_method' => request()->input('signature_method', 'draw'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        // Update field values
        foreach ($fieldValues as $fieldData) {
            if (!isset($fieldData['id'])) {
                continue;
            }

            SigningField::where('id', $fieldData['id'])
                ->where('assigned_to_signer_id', $signer->id)
                ->update([
                    'value' => $fieldData['value'] ?? null,
                    'filled_at' => now(),
                ]);
        }

        SigningAuditLog::create([
            'signing_session_id' => $signer->signing_session_id,
            'signer_id' => $signer->id,
            'event' => 'signed',
            'details' => [
                'signer_name' => $signer->signer_name,
                'signature_method' => $signer->signature_method,
                'fields_filled' => count($fieldValues),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Advance the signing session to the next signer or complete it.
     */
    public function advanceSession(SigningSession $session): void
    {
        $session->load('signers');

        if ($session->signing_order === 'sequential') {
            // Find next pending or sent signer in order
            $nextSigner = $session->signers
                ->whereIn('status', ['pending', 'sent'])
                ->sortBy('signing_order')
                ->first();

            if ($nextSigner) {
                if ($nextSigner->status === 'pending') {
                    $this->sendToSigner($nextSigner);
                }
                return;
            }
        }

        // Check if all signers have signed
        $allSigned = $session->signers->every(fn ($s) => $s->status === 'signed');

        if ($allSigned) {
            $this->completeSession($session);
        }
    }

    /**
     * Complete a signing session.
     *
     * Overlays all captured signatures onto the contract PDF, generates an
     * audit certificate, computes the final document hash, and stores the
     * sealed PDF before marking the session as completed.
     */
    public function completeSession(SigningSession $session): void
    {
        $session->load('signers.fields', 'contract');

        $pdfService = app(PdfService::class);

        // --- 1. Overlay signatures onto the contract PDF ----------------------
        $signatures = [];
        foreach ($session->signers as $signer) {
            if (!$signer->signature_image_path) {
                continue;
            }

            // Collect positioned signature fields for this signer
            $signatureFields = $signer->fields
                ->whereIn('field_type', ['signature', 'initials'])
                ->values();

            if ($signatureFields->isNotEmpty()) {
                // Place signature at each designated field position
                foreach ($signatureFields as $field) {
                    $signatures[] = [
                        'page' => $field->page_number,
                        'image_path' => $signer->signature_image_path,
                        'x' => (float) $field->x_position,
                        'y' => (float) $field->y_position,
                        'width' => (float) $field->width,
                        'height' => (float) $field->height,
                    ];
                }
            } else {
                // No positioned fields — overlay on last page with sensible defaults
                $pageCount = $pdfService->getPageCount($session->contract->storage_path);
                $signatures[] = [
                    'page' => $pageCount,
                    'image_path' => $signer->signature_image_path,
                    'x' => 20,
                    'y' => 240 - (30 * ($signer->signing_order ?? 0)),
                    'width' => 60,
                    'height' => 20,
                ];
            }
        }

        $finalStoragePath = $pdfService->overlaySignatures(
            $session->contract->storage_path,
            $signatures,
        );

        // --- 2. Generate the audit certificate --------------------------------
        $pdfService->generateAuditCertificate($session);

        // --- 3. Compute final document hash -----------------------------------
        $finalContent = Storage::disk(config('ccrs.contracts_disk', 's3'))
            ->get($finalStoragePath);
        $finalHash = $pdfService->computeHash($finalContent);

        // --- 4. Update session with final artefacts ---------------------------
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'final_storage_path' => $finalStoragePath,
            'final_document_hash' => $finalHash,
        ]);

        // Update contract signing status
        $session->contract->update(['signing_status' => 'signed']);

        SigningAuditLog::create([
            'signing_session_id' => $session->id,
            'event' => 'completed',
            'details' => [
                'contract_id' => $session->contract_id,
                'signer_count' => $session->signers->count(),
                'final_storage_path' => $finalStoragePath,
                'final_document_hash' => $finalHash,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        // Send completion email to all signers and initiator
        $session->load('initiator');

        foreach ($session->signers as $signer) {
            Mail::to($signer->signer_email)->send(new SigningComplete($session));
        }

        if ($session->initiator && $session->initiator->email) {
            Mail::to($session->initiator->email)->send(new SigningComplete($session));
        }
    }

    /**
     * Cancel a signing session.
     */
    public function cancelSession(SigningSession $session): void
    {
        $session->update(['status' => 'cancelled']);

        SigningAuditLog::create([
            'signing_session_id' => $session->id,
            'event' => 'cancelled',
            'details' => [
                'cancelled_by' => auth()->user()?->name,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Re-send signing invitation to a signer.
     */
    public function sendReminder(SigningSessionSigner $signer): void
    {
        Mail::to($signer->signer_email)->send(new SigningInvitation($signer));

        SigningAuditLog::create([
            'signing_session_id' => $signer->signing_session_id,
            'signer_id' => $signer->id,
            'event' => 'reminder_sent',
            'details' => [
                'signer_name' => $signer->signer_name,
                'signer_email' => $signer->signer_email,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
