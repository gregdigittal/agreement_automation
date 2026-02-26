<?php

namespace App\Http\Controllers;

use App\Models\SigningAuditLog;
use App\Models\StoredSignature;
use App\Services\SigningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
        // Pass the raw token (from URL) so views can build signing URLs without exposing the hash
        $rawToken = $token;

        // Load stored signatures for this signer (by email match)
        $storedSignatures = StoredSignature::forSigner(null, $signer->signer_email)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return view('signing.show', compact('signer', 'session', 'contract', 'rawToken', 'storedSignatures'));
    }

    public function submit(Request $request, string $token)
    {
        try {
            $signer = $this->signingService->validateToken($token);
        } catch (\RuntimeException $e) {
            return response()->view('signing.error', ['message' => $e->getMessage()], 403);
        }

        $request->validate([
            'signature_image' => 'required|string|max:500000', // C4: ~375KB decoded limit
            'signature_method' => 'required|in:draw,type,upload,webcam',
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

    /**
     * C3: Serve the contract PDF to external signers (no auth required, token-based).
     */
    public function document(string $token)
    {
        try {
            $signer = $this->signingService->validateToken($token);
        } catch (\RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        $contract = $signer->session->contract;
        $storagePath = $contract->storage_path;
        $disk = config('ccrs.contracts_disk', 's3');

        if (!$storagePath || !Storage::disk($disk)->exists($storagePath)) {
            abort(404, 'Document not found');
        }

        return Storage::disk($disk)->response($storagePath, $contract->title . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function decline(Request $request, string $token)
    {
        try {
            $signer = $this->signingService->validateToken($token);
        } catch (\RuntimeException $e) {
            return response()->view('signing.error', ['message' => $e->getMessage()], 403);
        }

        $request->validate(['reason' => 'nullable|string|max:1000']);

        DB::transaction(function () use ($signer, $request) {
            $signer = $signer->lockForUpdate()->find($signer->id);

            if ($signer->status === 'declined') {
                return;
            }

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
        });

        // Notify the initiator that a signer declined
        try {
            $initiator = $signer->session->initiator;
            if ($initiator?->email) {
                Mail::raw(
                    "Signer {$signer->signer_name} ({$signer->signer_email}) has declined to sign: {$signer->session->contract->title}. Reason: " . ($request->input('reason') ?? 'No reason provided.'),
                    fn ($msg) => $msg->to($initiator->email)->subject('Signing Declined: ' . $signer->session->contract->title)
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send decline notification email', [
                'session_id' => $signer->signing_session_id,
                'error' => $e->getMessage(),
            ]);
        }

        return view('signing.declined');
    }
}
