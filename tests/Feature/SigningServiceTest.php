<?php

use App\Mail\SigningComplete;
use App\Mail\SigningInvitation;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuditLog;
use App\Models\SigningSession;
use App\Models\SigningSessionSigner;
use App\Models\User;
use App\Services\PdfService;
use App\Services\SigningService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('s3');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $region = Region::create(['name' => 'Test Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Test Entity']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Test Project']);
    $cp = Counterparty::create(['legal_name' => 'Test CP', 'status' => 'Active']);

    // Upload a fake PDF so ContractFileService::download works
    Storage::disk('s3')->put('contracts/test.pdf', '%PDF-1.4 fake pdf content');

    $this->contract = Contract::create([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial',
        'title' => 'Signing Test Contract',
        'storage_path' => 'contracts/test.pdf',
        'file_name' => 'test.pdf',
    ]);

    $this->service = app(SigningService::class);
});

it('creates a signing session with signers', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'internal', 'order' => 1],
    ], 'sequential');

    expect($session->status)->toBe('active');
    expect($session->signers)->toHaveCount(2);
    expect($session->signing_order)->toBe('sequential');

    $auditLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'created')
        ->first();
    expect($auditLog)->not->toBeNull();
});

it('generates unique tokens when sending to signer', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    // Raw token is 64 hex chars (32 bytes); DB stores SHA-256 hash (also 64 hex chars)
    expect($rawToken)->toHaveLength(64);
    $signer->refresh();
    expect($signer->token)->toHaveLength(64);
    // The stored token must be the hash of the raw token
    expect($signer->token)->toBe(hash('sha256', $rawToken));
    expect($signer->token_expires_at)->not->toBeNull();
    expect((int) abs($signer->token_expires_at->diffInDays(now())))->toBeGreaterThanOrEqual(6);
    expect($signer->status)->toBe('sent');

    Mail::assertSent(SigningInvitation::class);
});

it('validates a valid token', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    // Validate using the raw token (service hashes it internally)
    $validatedSigner = $this->service->validateToken($rawToken);

    expect($validatedSigner->id)->toBe($signer->id);
    expect($validatedSigner->fresh()->viewed_at)->not->toBeNull();
});

it('rejects expired tokens', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    // Manually expire the token
    $signer->update(['token_expires_at' => now()->subDay()]);

    $this->service->validateToken($rawToken);
})->throws(\RuntimeException::class, 'This signing link has expired.');

it('rejects re-signing after already signed', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->update(['status' => 'signed']);

    $this->service->validateToken($rawToken);
})->throws(\RuntimeException::class, 'You have already signed this document.');

it('rejects token after signer declined', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->update(['status' => 'declined']);

    $this->service->validateToken($rawToken);
})->throws(\RuntimeException::class, 'You have declined to sign this document.');

it('enforces session expiry', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    // Manually expire the session
    $session->update(['expires_at' => now()->subDay()]);

    $this->service->validateToken($rawToken);
})->throws(\RuntimeException::class, 'This signing session has expired.');

it('captures signature and updates signer', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    // Validate token first (sets viewed_at)
    $this->service->validateToken($rawToken);

    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $this->service->captureSignature($signer, [], $base64Png);

    $signer->refresh();
    expect($signer->status)->toBe('signed');
    expect($signer->signature_image_path)->not->toBeNull();
    expect($signer->signed_at)->not->toBeNull();

    // Verify signature image was stored
    Storage::disk('s3')->assertExists($signer->signature_image_path);
});

it('rejects invalid base64 signature data', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $this->service->sendToSigner($signer);
    $signer->refresh();

    // Pass invalid base64 that is not a valid image
    $this->service->captureSignature($signer, [], 'dGhpcyBpcyBub3QgYW4gaW1hZ2U=');
})->throws(\InvalidArgumentException::class, 'Signature must be a valid PNG or JPEG image.');

it('completes session when all signers done', function () {
    // Mock PdfService so FPDI does not attempt to parse the fake PDF
    $mockPdf = Mockery::mock(PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/final.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'sealed-pdf'));
    app()->instance(PdfService::class, $mockPdf);

    // Place a fake final PDF so Storage::get works during hash computation
    Storage::disk('s3')->put('contracts/signed/final.pdf', '%PDF-sealed-content');

    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $this->service->captureSignature($signer, [], $base64Png);
    $this->service->advanceSession($session->fresh());

    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($session->final_storage_path)->toBe('contracts/signed/final.pdf');
    expect($session->final_document_hash)->not->toBeNull();
    expect($this->contract->fresh()->signing_status)->toBe('signed');

    Mail::assertSent(SigningComplete::class);
});

it('sends to next signer in sequential mode', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'external', 'order' => 1],
    ], 'sequential');

    // Send to first signer and have them sign
    $signer1 = $session->signers->sortBy('signing_order')->first();
    $rawToken1 = $this->service->sendToSigner($signer1);
    $signer1->refresh();

    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $this->service->captureSignature($signer1, [], $base64Png);

    // Reset mail fake to track new sends
    Mail::fake();

    // Advance should send to second signer
    $this->service->advanceSession($session->fresh());

    $signer2 = $session->signers->sortBy('signing_order')->skip(1)->first()->fresh();
    expect($signer2->token)->not->toBeNull();
    expect($signer2->status)->toBe('sent');

    Mail::assertSent(SigningInvitation::class, function (SigningInvitation $mail) {
        return $mail->signer->signer_email === 'bob@example.com';
    });
});

it('cancels session on cancel', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $this->service->cancelSession($session);

    $session->refresh();
    expect($session->status)->toBe('cancelled');

    $auditLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'cancelled')
        ->first();
    expect($auditLog)->not->toBeNull();
});

it('creates audit log entries for each action', function () {
    // Mock PdfService for the completion step
    $mockPdf = Mockery::mock(PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/audit-test.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'audit-test'));
    app()->instance(PdfService::class, $mockPdf);

    Storage::disk('s3')->put('contracts/signed/audit-test.pdf', '%PDF-audit-test');

    // 1. Create session — should log 'created'
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $createdLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'created')
        ->first();
    expect($createdLog)->not->toBeNull();
    expect($createdLog->details)->toHaveKey('signer_count', 1);

    // 2. Send to signer — should log 'sent'
    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $sentLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'sent')
        ->first();
    expect($sentLog)->not->toBeNull();
    expect($sentLog->signer_id)->toBe($signer->id);

    // 3. Validate token / view — should log 'viewed'
    $this->service->validateToken($rawToken);

    $viewedLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'viewed')
        ->first();
    expect($viewedLog)->not->toBeNull();
    expect($viewedLog->signer_id)->toBe($signer->id);

    // 4. Capture signature — should log 'signed'
    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $this->service->captureSignature($signer, [], $base64Png);

    $signedLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'signed')
        ->first();
    expect($signedLog)->not->toBeNull();
    expect($signedLog->signer_id)->toBe($signer->id);

    // 5. Advance session (completes since only one signer) — should log 'completed'
    $this->service->advanceSession($session->fresh());

    $completedLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'completed')
        ->first();
    expect($completedLog)->not->toBeNull();

    // Verify the full set of audit events in chronological order
    $allEvents = SigningAuditLog::where('signing_session_id', $session->id)
        ->orderBy('created_at')
        ->pluck('event')
        ->toArray();
    expect($allEvents)->toContain('created', 'sent', 'viewed', 'signed', 'completed');
});
