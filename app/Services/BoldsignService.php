<?php

namespace App\Services;

use App\Models\BoldsignEnvelope;
use App\Models\Contract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * @deprecated This service is deprecated in favour of the in-house SigningService.
 *
 * When the feature flag FEATURE_IN_HOUSE_SIGNING=true (default), all e-signing
 * is handled by \App\Services\SigningService and the BoldSign integration is
 * disabled. This class is retained for backward-compatibility with deployments
 * that still rely on BoldSign while transitioning to in-house signing.
 *
 * @see \App\Services\SigningService
 * @see \App\Helpers\Feature::inHouseSigning()
 */
class BoldsignService
{
    public function sendToSign(Contract $contract, array $signers, string $signingOrder = 'sequential'): BoldsignEnvelope
    {
        $response = Http::withToken(config('ccrs.boldsign_api_key'))
            ->post(config('ccrs.boldsign_api_url') . '/v1/document/send', [
                'title' => $contract->title ?? 'Contract',
                'signerDetails' => $signers,
                'signingOrder' => $signingOrder,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Boldsign API error: ' . $response->body());
        }

        $documentId = $response->json('documentId');

        return DB::transaction(function () use ($contract, $documentId, $signingOrder, $signers) {
            $envelope = BoldsignEnvelope::create([
                'contract_id' => $contract->id,
                'boldsign_document_id' => $documentId,
                'status' => 'sent',
                'signing_order' => $signingOrder,
                'signers' => $signers,
                'sent_at' => now(),
            ]);

            $contract->signing_status = 'sent';
            $contract->save();

            AuditService::log('contract_sent_to_sign', 'contract', $contract->id, [
                'envelope_id' => $envelope->id,
                'document_id' => $documentId,
            ]);

            return $envelope;
        });
    }

    public function getSigningStatus(string $documentId): array
    {
        $response = Http::withToken(config('ccrs.boldsign_api_key'))
            ->get(config('ccrs.boldsign_api_url') . '/v1/document/properties', [
                'documentId' => $documentId,
            ]);
        return $response->successful() ? $response->json() : [];
    }

    public function handleWebhook(array $payload): void
    {
        $documentId = $payload['documentId'] ?? $payload['DocumentId'] ?? null;
        if (!$documentId) return;

        $envelope = BoldsignEnvelope::where('boldsign_document_id', $documentId)->first();
        if (!$envelope) return;

        $status = $payload['event'] ?? $payload['Event'] ?? $payload['status'] ?? 'unknown';
        $statusMap = [
            'Completed' => 'completed',
            'DocumentCompleted' => 'completed',
            'Declined' => 'declined',
            'Expired' => 'expired',
            'Viewed' => 'viewed',
            'PartiallySigned' => 'partially_signed',
        ];
        $newStatus = $statusMap[$status] ?? $envelope->status;

        $envelope->update([
            'status' => $newStatus,
            'webhook_payload' => $payload,
            'completed_at' => $newStatus === 'completed' ? now() : $envelope->completed_at,
        ]);

        if ($newStatus === 'completed') {
            $contract = $envelope->contract;
            $contract->signing_status = 'completed';
            $contract->workflow_state = 'executed';
            $contract->save();
        }

        AuditService::log('boldsign_webhook', 'boldsign_envelope', $envelope->id, ['status' => $newStatus]);
    }

    /**
     * Create a BoldSign envelope for countersigning — only internal Digittal signers.
     * The uploaded document already has the counterparty's signature.
     */
    public function createCountersignEnvelope(Contract $contract, array $internalSigners): BoldsignEnvelope
    {
        $storagePath = $contract->storage_path;
        if (!$storagePath) {
            throw new \RuntimeException("Contract {$contract->id} has no uploaded document to countersign.");
        }

        $documentContents = Storage::disk(config('ccrs.contracts_disk', 'database'))->get($storagePath);
        if (!$documentContents) {
            throw new \RuntimeException("Failed to download document from storage: {$storagePath}");
        }

        $signers = collect($internalSigners)->map(fn (array $signer, int $index) => [
            'name' => $signer['name'],
            'emailAddress' => $signer['email'],
            'signerOrder' => $signer['order'] ?? ($index + 1),
            'signerType' => 'Signer',
        ])->values()->toArray();

        $response = Http::withToken(config('ccrs.boldsign_api_key'))
            ->attach('Files', $documentContents, basename($storagePath))
            ->post(config('ccrs.boldsign_api_url') . '/v1/document/send', [
                'title' => "Countersign: " . ($contract->title ?? 'Contract'),
                'signers' => $signers,
                'enableSigningOrder' => true,
                'message' => 'Please countersign this contract on behalf of Digittal.',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('BoldSign API error creating countersign envelope: ' . $response->body());
        }

        $data = $response->json();

        $envelope = BoldsignEnvelope::create([
            'contract_id' => $contract->id,
            'boldsign_document_id' => $data['documentId'] ?? $data['id'],
            'status' => 'sent',
            'is_countersign' => true,
            'signers' => $internalSigners,
            'sent_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $contract->signing_status = 'countersign_sent';
        $contract->save();

        AuditService::log('contract_sent_to_countersign', 'contract', $contract->id, [
            'envelope_id' => $envelope->id,
            'document_id' => $data['documentId'] ?? $data['id'],
        ]);

        return $envelope;
    }

    public function verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool
    {
        if (empty($signature) || empty($secret)) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}
