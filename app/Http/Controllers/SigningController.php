<?php

namespace App\Http\Controllers;

use App\Models\SigningAuditLog;
use App\Services\SigningService;
use Illuminate\Http\Request;

class SigningController extends Controller
{
    public function __construct(private SigningService $signingService) {}

    public function show(string $token)
    {
        try {
            $signer = $this->signingService->validateToken($token);
        } catch (\RuntimeException $e) {
            return response()->view('signing.error', ['message' => $e->getMessage()], 403);
        }

        $session = $signer->session()->with(['fields' => fn ($q) => $q->where('assigned_to_signer_id', $signer->id)])->first();
        $contract = $session->contract;

        return view('signing.show', compact('signer', 'session', 'contract'));
    }

    public function submit(Request $request, string $token)
    {
        try {
            $signer = $this->signingService->validateToken($token);
        } catch (\RuntimeException $e) {
            return response()->view('signing.error', ['message' => $e->getMessage()], 403);
        }

        $request->validate([
            'signature_image' => 'required|string',
            'signature_method' => 'required|in:draw,type,upload',
            'fields' => 'array',
            'fields.*.id' => 'required|string',
            'fields.*.value' => 'nullable|string',
        ]);

        $this->signingService->captureSignature(
            $signer,
            $request->input('fields', []),
            $request->input('signature_image'),
        );

        $this->signingService->advanceSession($signer->session);

        return view('signing.complete', ['signer' => $signer]);
    }

    public function decline(Request $request, string $token)
    {
        try {
            $signer = $this->signingService->validateToken($token);
        } catch (\RuntimeException $e) {
            return response()->view('signing.error', ['message' => $e->getMessage()], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);

        $signer->update(['status' => 'declined']);

        SigningAuditLog::create([
            'signing_session_id' => $signer->signing_session_id,
            'signer_id' => $signer->id,
            'event' => 'declined',
            'details' => ['reason' => $request->input('reason')],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $signer->session->update(['status' => 'cancelled']);

        return view('signing.declined');
    }
}
