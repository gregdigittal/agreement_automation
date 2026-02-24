<?php

namespace App\Services;

use App\Models\BoldsignEnvelope;
use App\Models\Contract;
use Illuminate\Support\Facades\Http;

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
        $envelope = BoldsignEnvelope::create([
            'contract_id' => $contract->id,
            'boldsign_document_id' => $documentId,
            'status' => 'sent',
            'signing_order' => $signingOrder,
            'signers' => $signers,
            'sent_at' => now(),
        ]);

        $contract->update(['signing_status' => 'sent']);

        AuditService::log('contract_sent_to_sign', 'contract', $contract->id, [
            'envelope_id' => $envelope->id,
            'document_id' => $documentId,
        ]);

        return $envelope;
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
            $contract->update([
                'signing_status' => 'completed',
                'workflow_state' => 'executed',
            ]);
        }

        AuditService::log('boldsign_webhook', 'boldsign_envelope', $envelope->id, ['status' => $newStatus]);
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
