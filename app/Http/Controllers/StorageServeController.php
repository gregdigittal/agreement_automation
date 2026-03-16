<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\FileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageServeController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse|RedirectResponse
    {
        $this->authorise($path);

        $disk = config('ccrs.contracts_disk');

        if ($disk !== 'database') {
            // S3 / SeaweedFS: redirect to a short-lived pre-signed URL.
            // The client downloads directly from object storage — no PHP proxying needed.
            if (! Storage::disk($disk)->exists($path)) {
                abort(404, 'File not found.');
            }

            $expiresAt = now()->addMinutes(5);
            $temporaryUrl = Storage::disk($disk)->temporaryUrl($path, $expiresAt);

            return redirect()->away($temporaryUrl);
        }

        // database disk: stream BLOB from the file_storage table.
        $file = FileStorage::where('path', $path)->first();

        if (! $file) {
            abort(404, 'File not found.');
        }

        $disposition = $request->query('download') ? 'attachment' : 'inline';
        $filename = basename($path);

        return new StreamedResponse(
            function () use ($file) {
                echo $file->contents;
            },
            200,
            [
                'Content-Type' => $file->mime_type ?? 'application/octet-stream',
                'Content-Length' => $file->size,
                'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
                'Cache-Control' => 'private, max-age=300',
            ]
        );
    }

    /**
     * Enforce path-level authorization for files stored under contracts/.
     *
     * - web guard (staff): system_admin sees all; others are blocked from restricted contracts
     *   unless they are in the contract's authorizedUsers list.
     * - vendor guard: may only access files belonging to their own counterparty's contracts.
     * - Other path prefixes (side_letters/, templates/, signatures/, etc.): authentication via
     *   the route middleware is the only requirement — the signed URL is the access boundary.
     */
    private function authorise(string $path): void
    {
        // Only contract documents require fine-grained authorization.
        // Path format: contracts/{contractId}/{filename}
        if (! str_starts_with($path, 'contracts/')) {
            return;
        }

        $parts = explode('/', $path);
        if (count($parts) < 2) {
            return;
        }

        $contractId = $parts[1];

        if ($webUser = auth('web')->user()) {
            if ($webUser->hasRole('system_admin')) {
                return;
            }

            $contract = Contract::find($contractId);

            if (! $contract) {
                abort(404, 'Contract not found.');
            }

            if ($contract->is_restricted && ! $contract->authorizedUsers()->where('users.id', $webUser->id)->exists()) {
                abort(403, 'You are not authorised to access this file.');
            }

            return;
        }

        if ($vendorUser = auth('vendor')->user()) {
            $contract = Contract::find($contractId);

            if (! $contract || $contract->counterparty_id !== $vendorUser->counterparty_id) {
                abort(403, 'You are not authorised to access this file.');
            }

            return;
        }

        // No authenticated guard resolved — should not reach here due to route middleware,
        // but abort defensively.
        abort(401, 'Unauthenticated.');
    }
}
