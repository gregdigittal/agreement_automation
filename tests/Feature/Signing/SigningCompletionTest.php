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
use App\Models\StoredSignature;
use App\Models\User;
use App\Services\PdfService;
use App\Services\SigningService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake(config('ccrs.contracts_disk'));
    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);

    $region = Region::create(['name' => 'Completion Test Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Completion Test Entity']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Completion Test Project']);
    $cp = Counterparty::create(['legal_name' => 'Completion Test CP', 'status' => 'Active']);

    Storage::disk(config('ccrs.contracts_disk'))->put('contracts/completion-test.pdf', '%PDF-1.4 fake completion test pdf');

    $this->contract = Contract::create([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial',
        'title' => 'Completion Test Contract',
        'storage_path' => 'contracts/completion-test.pdf',
        'file_name' => 'completion-test.pdf',
    ]);

    $this->service = app(SigningService::class);
    $this->base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    // Mock PdfService for all tests that need completion
    $mockPdf = Mockery::mock(PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/completion-final.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'completion-sealed'));
    app()->instance(PdfService::class, $mockPdf);
    Storage::disk(config('ccrs.contracts_disk'))->put('contracts/signed/completion-final.pdf', '%PDF-sealed');
});

// ── Signature Submission ────────────────────────────────────────────────

it('1. submit signature via draw method through controller', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    // View page first (sets viewed_at)
    $this->get(route('signing.show', ['token' => $rawToken]));

    $response = $this->post(route('signing.submit', ['token' => $rawToken]), [
        'signature_image' => $this->base64Png,
        'signature_method' => 'draw',
        'fields' => [],
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('signing.complete');

    $signer->refresh();
    expect($signer->status)->toBe('signed');
    expect($signer->signature_image_path)->not->toBeNull();
});

it('2. submit signature via typed method', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->get(route('signing.show', ['token' => $rawToken]));

    $response = $this->post(route('signing.submit', ['token' => $rawToken]), [
        'signature_image' => $this->base64Png,
        'signature_method' => 'type',
        'fields' => [],
    ]);

    $response->assertStatus(200);
    $signer->refresh();
    expect($signer->status)->toBe('signed');
});

it('3. submit signature via upload method', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Carol', 'email' => 'carol@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->get(route('signing.show', ['token' => $rawToken]));

    $response = $this->post(route('signing.submit', ['token' => $rawToken]), [
        'signature_image' => $this->base64Png,
        'signature_method' => 'upload',
        'fields' => [],
    ]);

    $response->assertStatus(200);
    $signer->refresh();
    expect($signer->status)->toBe('signed');
});

it('4. signing changes signer status to signed', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Dave', 'email' => 'dave@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    expect($signer->status)->toBe('sent');

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);

    $signer->refresh();
    expect($signer->status)->toBe('signed');
    expect($signer->signed_at)->not->toBeNull();
    expect($signer->signature_image_path)->not->toBeNull();
});

it('5. signature image is stored on disk', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Eve', 'email' => 'eve@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);

    $signer->refresh();
    Storage::disk(config('ccrs.contracts_disk'))->assertExists($signer->signature_image_path);
});

// ── Declining ───────────────────────────────────────────────────────────

it('6. signer can decline with reason', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Frank', 'email' => 'frank@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    $response = $this->post(route('signing.decline', ['token' => $rawToken]), [
        'reason' => 'Terms are unacceptable',
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('signing.declined');
});

it('7. declining sets signer status to declined and session to cancelled', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Grace', 'email' => 'grace@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    $this->post(route('signing.decline', ['token' => $rawToken]), [
        'reason' => 'Not acceptable',
    ]);

    expect($signer->fresh()->status)->toBe('declined');
    expect($session->fresh()->status)->toBe('cancelled');
});

it('8. declining creates audit log entry', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Henry', 'email' => 'henry@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    $this->post(route('signing.decline', ['token' => $rawToken]), [
        'reason' => 'Disagree',
    ]);

    $declineLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'declined')
        ->first();
    expect($declineLog)->not->toBeNull();
    expect($declineLog->signer_id)->toBe($signer->id);
});

// ── Session Completion ──────────────────────────────────────────────────

it('9. all signers signed triggers session completion', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);
    $this->service->advanceSession($session->fresh());

    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($session->completed_at)->not->toBeNull();
});

it('10. completion generates signed document with overlay', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);
    $this->service->advanceSession($session->fresh());

    $session->refresh();
    expect($session->final_storage_path)->toBe('contracts/signed/completion-final.pdf');
});

it('11. completion computes final document hash', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);
    $this->service->advanceSession($session->fresh());

    $session->refresh();
    expect($session->final_document_hash)->not->toBeNull();
    expect($session->final_document_hash)->toHaveLength(64); // SHA-256
});

it('12. completion sends emails to all participants', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);
    $this->service->advanceSession($session->fresh());

    Mail::assertSent(SigningComplete::class);
});

it('13. completion advances contract signing_status to signed', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);
    $this->service->advanceSession($session->fresh());

    expect($this->contract->fresh()->signing_status)->toBe('signed');
});

// ── Reminders & Cancellation ────────────────────────────────────────────

it('14. reminder sends new token to pending signer and invalidates old one', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Iris', 'email' => 'iris@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $originalRawToken = $this->service->sendToSigner($signer);
    $signer->refresh();
    $originalHashedToken = $signer->token;

    $reminderRawToken = $this->service->sendReminder($signer);
    $signer->refresh();

    // New token generated
    expect($reminderRawToken)->toHaveLength(64);
    expect($reminderRawToken)->not->toBe($originalRawToken);

    // New hash stored
    expect($signer->token)->toBe(hash('sha256', $reminderRawToken));
    expect($signer->token)->not->toBe($originalHashedToken);

    // New token works
    $validatedSigner = $this->service->validateToken($reminderRawToken);
    expect($validatedSigner->id)->toBe($signer->id);

    // Old token no longer works
    expect(fn () => $this->service->validateToken($originalRawToken))
        ->toThrow(\RuntimeException::class, 'Invalid signing token.');

    // Audit log for reminder
    $reminderLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'reminder_sent')
        ->first();
    expect($reminderLog)->not->toBeNull();
});

it('15. cancellation invalidates session and creates audit log', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Jack', 'email' => 'jack@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    $this->service->cancelSession($session);

    $session->refresh();
    expect($session->status)->toBe('cancelled');

    $cancelLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'cancelled')
        ->first();
    expect($cancelLog)->not->toBeNull();

    // Token should no longer work (session is no longer active)
    expect(fn () => $this->service->validateToken($rawToken))
        ->toThrow(\RuntimeException::class);
});

it('16. cancellation preserves existing signatures', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'external', 'order' => 1],
    ], 'sequential');

    // First signer signs
    $signer1 = $session->signers->sortBy('signing_order')->first();
    $rawToken1 = $this->service->sendToSigner($signer1);
    $signer1->refresh();
    $this->service->validateToken($rawToken1);
    $this->service->captureSignature($signer1, [], $this->base64Png);
    expect($signer1->fresh()->status)->toBe('signed');

    // Cancel session before second signer
    $this->service->cancelSession($session);

    // First signer's signature data is preserved
    $signer1->refresh();
    expect($signer1->status)->toBe('signed');
    expect($signer1->signature_image_path)->not->toBeNull();
    expect($signer1->signed_at)->not->toBeNull();
});

// ── Audit Trail ─────────────────────────────────────────────────────────

it('17. every action creates SigningAuditLog entry', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Audit Signer', 'email' => 'audit@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    // 1. Session created
    $createdLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'created')
        ->first();
    expect($createdLog)->not->toBeNull();

    // 2. Send to signer
    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);
    $signer->refresh();

    $sentLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'sent')
        ->first();
    expect($sentLog)->not->toBeNull();

    // 3. Validate token / view
    $this->service->validateToken($rawToken);

    $viewedLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'viewed')
        ->first();
    expect($viewedLog)->not->toBeNull();

    // 4. Sign
    $this->service->captureSignature($signer, [], $this->base64Png);

    $signedLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'signed')
        ->first();
    expect($signedLog)->not->toBeNull();

    // 5. Complete
    $this->service->advanceSession($session->fresh());

    $completedLog = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'completed')
        ->first();
    expect($completedLog)->not->toBeNull();

    // Verify full event sequence
    $allEvents = SigningAuditLog::where('signing_session_id', $session->id)
        ->orderBy('created_at')
        ->pluck('event')
        ->toArray();
    expect($allEvents)->toContain('created', 'sent', 'viewed', 'signed', 'completed');
});
