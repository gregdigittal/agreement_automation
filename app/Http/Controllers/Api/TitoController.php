<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Feature;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\AuditService;
use App\Services\TelemetryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TitoController extends Controller
{
    public function validate(Request $request)
    {
        $request->validate([
            'vendor_id'  => ['required', 'uuid'],
            'entity_id'  => ['sometimes', 'uuid'],
            'region_id'  => ['sometimes', 'uuid'],
            'project_id' => ['sometimes', 'uuid'],
        ]);

        $vendorId   = $request->query('vendor_id');
        $entityId   = $request->query('entity_id');
        $regionId   = $request->query('region_id');
        $projectId  = $request->query('project_id');

        $span = TelemetryService::startSpan('tito.validate', ['vendor_id' => $vendorId]);
        try {
            $cacheKey = 'tito:' . md5(implode('|', array_filter([
            $vendorId, $entityId, $regionId, $projectId
        ])));

        $result = Cache::remember($cacheKey, now()->addMinutes(5), function () use (
            $vendorId, $entityId, $regionId, $projectId
        ) {
            $query = Contract::query()
                ->where('contract_type', 'Merchant')
                ->where('counterparty_id', $vendorId)
                ->where(function ($q) {
                    // Support both in-house signing sessions and legacy BoldSign envelopes
                    $q->whereHas('signingSessions', fn ($sq) => $sq->where('status', 'completed'))
                      ->orWhereHas('boldsignEnvelopes', fn ($sq) => $sq->where('status', 'completed'));
                });

            if ($entityId)  $query->where('entity_id', $entityId);
            if ($regionId)  $query->where('region_id', $regionId);
            if ($projectId) $query->where('project_id', $projectId);

            $contract = $query
                ->orderByDesc('created_at')
                ->select(['id', 'workflow_state', 'created_at'])
                ->with([
                    'signingSessions' => fn ($q) =>
                        $q->where('status', 'completed')
                          ->orderByDesc('completed_at')
                          ->select(['id', 'contract_id', 'status', 'completed_at']),
                    'boldsignEnvelopes' => fn ($q) =>
                        $q->where('status', 'completed')
                          ->orderByDesc('updated_at')
                          ->select(['id', 'contract_id', 'status', 'updated_at']),
                ])
                ->first();

            if (! $contract) {
                return [
                    'valid'       => false,
                    'status'      => 'no_signed_agreement',
                    'contract_id' => null,
                    'signed_at'   => null,
                ];
            }

            // Prefer in-house signing session timestamp, fall back to BoldSign envelope
            $signingSession = $contract->signingSessions->first();
            $envelope = $contract->boldsignEnvelopes->first();
            $signedAt = $signingSession?->completed_at ?? $envelope?->updated_at;

            return [
                'valid'       => true,
                'status'      => 'signed',
                'contract_id' => $contract->id,
                'signed_at'   => $signedAt?->toIso8601String(),
            ];
        });

            AuditService::log(
                action: 'tito.validate',
                resourceType: 'contract',
                resourceId: $result['contract_id'],
                details: [
                    'vendor_id'  => $vendorId,
                    'entity_id'  => $entityId,
                    'region_id'  => $regionId,
                    'project_id' => $projectId,
                    'valid'      => $result['valid'],
                ]
            );

            return response()->json($result);
        } finally {
            $span?->end();
        }
    }
}
