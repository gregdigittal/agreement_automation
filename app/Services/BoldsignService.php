<?php

namespace App\Services;

use App\Models\BoldsignEnvelope;
use App\Models\Contract;
use Illuminate\Support\Facades\Http;

class BoldsignService
{
    public function sendToSign(Contract $contract, array $signers): BoldsignEnvelope
    {
        $envelope = BoldsignEnvelope::create([
            'contract_id' => $contract->id,
            'status' => 'sent',
            'signers' => $signers,
            'sent_at' => now(),
        ]);

        try {
            $response = Http::withToken(config('ccrs.boldsign_api_key'))
                ->post(config('ccrs.boldsign_api_url') . '/v1/document/send', [
                    'title' => $contract->title ?? 'Contract',
                    'signerDetails' => $signers,
                ]);

            if ($response->successful()) {
                $envelope->update([
                    'boldsign_document_id' => $response->json('documentId'),
                ]);
            }
        } catch (\Exception $e) {
            $envelope->update(['status' => 'draft']);
            throw $e;
        }

        AuditService::log('contract_sent_to_sign', 'contract', $contract->id, [
            'envelope_id' => $envelope->id,
            'signer_count' => count($signers),
        ]);

        return $envelope;
    }

    public function handleWebhook(array $payload): void
    {
        $documentId = $payload['documentId'] ?? null;
        if (!$documentId) return;

        $envelope = BoldsignEnvelope::where('boldsign_document_id', $documentId)->first();
        if (!$envelope) return;

        $status = $payload['event'] ?? 'unknown';
        $statusMap = [
            'Completed' => 'completed',
            'Declined' => 'declined',
            'Expired' => 'expired',
            'Viewed' => 'viewed',
        ];

        $newStatus = $statusMap[$status] ?? $envelope->status;
        $envelope->update([
            'status' => $newStatus,
            'webhook_payload' => $payload,
            'completed_at' => $newStatus === 'completed' ? now() : $envelope->completed_at,
        ]);

        if ($newStatus === 'completed') {
            $envelope->contract->update(['signing_status' => 'completed']);
        }
    }
}
