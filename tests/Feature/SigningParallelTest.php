<?php

use App\Mail\SigningComplete;
use App\Mail\SigningInvitation;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuditLog;
use App\Models\User;
use App\Services\PdfService;
use App\Services\SigningService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('s3');
    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $region = Region::create(['name' => 'Parallel Test Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Parallel Test Entity']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Parallel Test Project']);
    $cp = Counterparty::create(['legal_name' => 'Parallel Test CP', 'status' => 'Active']);

    Storage::disk('s3')->put('contracts/parallel-test.pdf', '%PDF-1.4 fake parallel test pdf');

    $this->contract = Contract::create([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial',
        'title' => 'Parallel Signing Test',
        'storage_path' => 'contracts/parallel-test.pdf',
        'file_name' => 'parallel-test.pdf',
    ]);

    $this->service = app(SigningService::class);
    $this->base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    // Mock PdfService for completion
    $mockPdf = Mockery::mock(PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/parallel-final.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'parallel-sealed'));
    app()->instance(PdfService::class, $mockPdf);
    Storage::disk('s3')->put('contracts/signed/parallel-final.pdf', '%PDF-sealed');
});

it('completes parallel signing flow when all signers sign', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer B', 'email' => 'b@example.com', 'type' => 'external', 'order' => 1],
    ], 'parallel');

    expect($session->signing_order)->toBe('parallel');
    expect($session->signers)->toHaveCount(2);

    // In parallel mode, send invitations to all signers upfront
    $signerA = $session->signers->where('signer_email', 'a@example.com')->first();
    $signerB = $session->signers->where('signer_email', 'b@example.com')->first();

    $rawTokenA = $this->service->sendToSigner($signerA);
    $rawTokenB = $this->service->sendToSigner($signerB);

    Mail::assertSent(SigningInvitation::class, 2);

    // Signer A views and signs
    $this->service->validateToken($rawTokenA);
    $this->service->captureSignature($signerA, [], $this->base64Png);
    expect($signerA->fresh()->status)->toBe('signed');

    // Advance after signer A — should NOT complete (signer B still pending)
    $this->service->advanceSession($session->fresh());
    expect($session->fresh()->status)->toBe('active');

    // Signer B views and signs
    $this->service->validateToken($rawTokenB);
    $this->service->captureSignature($signerB, [], $this->base64Png);
    expect($signerB->fresh()->status)->toBe('signed');

    // Advance after signer B — should complete now
    $this->service->advanceSession($session->fresh());

    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($session->completed_at)->not->toBeNull();
    expect($this->contract->fresh()->signing_status)->toBe('signed');

    Mail::assertSent(SigningComplete::class);
});

it('does not complete parallel session until all signers have signed', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer B', 'email' => 'b@example.com', 'type' => 'external', 'order' => 1],
        ['name' => 'Signer C', 'email' => 'c@example.com', 'type' => 'external', 'order' => 2],
    ], 'parallel');

    $signers = $session->signers;
    $tokens = [];
    foreach ($signers as $signer) {
        $tokens[$signer->signer_email] = $this->service->sendToSigner($signer);
    }

    // Only A and B sign
    $this->service->validateToken($tokens['a@example.com']);
    $this->service->captureSignature($signers->where('signer_email', 'a@example.com')->first(), [], $this->base64Png);

    $this->service->validateToken($tokens['b@example.com']);
    $this->service->captureSignature($signers->where('signer_email', 'b@example.com')->first(), [], $this->base64Png);

    // Advance — C hasn't signed so session stays active
    $this->service->advanceSession($session->fresh());
    expect($session->fresh()->status)->toBe('active');

    // C signs
    $this->service->validateToken($tokens['c@example.com']);
    $this->service->captureSignature($signers->where('signer_email', 'c@example.com')->first(), [], $this->base64Png);

    $this->service->advanceSession($session->fresh());
    expect($session->fresh()->status)->toBe('completed');
});

it('prevents re-signing after signer has already signed', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
    ], 'parallel');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);
    expect($signer->fresh()->status)->toBe('signed');

    // Attempt to re-validate the same token should throw
    expect(fn () => $this->service->validateToken($rawToken))
        ->toThrow(\RuntimeException::class, 'You have already signed this document.');
});

it('cancels parallel session when one signer declines', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer B', 'email' => 'b@example.com', 'type' => 'external', 'order' => 1],
    ], 'parallel');

    $signerA = $session->signers->where('signer_email', 'a@example.com')->first();
    $signerB = $session->signers->where('signer_email', 'b@example.com')->first();

    $rawTokenA = $this->service->sendToSigner($signerA);
    $rawTokenB = $this->service->sendToSigner($signerB);

    // Signer A signs
    $this->service->validateToken($rawTokenA);
    $this->service->captureSignature($signerA, [], $this->base64Png);

    // Signer B declines via controller
    $response = $this->post(route('signing.decline', ['token' => $rawTokenB]), [
        'reason' => 'Disagree with terms',
    ]);

    $response->assertStatus(200);
    expect($signerB->fresh()->status)->toBe('declined');
    expect($session->fresh()->status)->toBe('cancelled');
});

it('prevents signing after session is cancelled by decline', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer B', 'email' => 'b@example.com', 'type' => 'external', 'order' => 1],
    ], 'parallel');

    $signerA = $session->signers->where('signer_email', 'a@example.com')->first();
    $signerB = $session->signers->where('signer_email', 'b@example.com')->first();

    $rawTokenA = $this->service->sendToSigner($signerA);
    $rawTokenB = $this->service->sendToSigner($signerB);

    // Signer B declines — cancels session
    $this->post(route('signing.decline', ['token' => $rawTokenB]), ['reason' => 'No']);
    expect($session->fresh()->status)->toBe('cancelled');

    // Signer A tries to view signing page — should get 403 (session inactive)
    $response = $this->get(route('signing.show', ['token' => $rawTokenA]));
    $response->assertStatus(403);
});

it('records correct audit trail for parallel signing flow', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer B', 'email' => 'b@example.com', 'type' => 'external', 'order' => 1],
    ], 'parallel');

    $signerA = $session->signers->where('signer_email', 'a@example.com')->first();
    $signerB = $session->signers->where('signer_email', 'b@example.com')->first();

    $rawTokenA = $this->service->sendToSigner($signerA);
    $rawTokenB = $this->service->sendToSigner($signerB);

    // Both view and sign
    $this->service->validateToken($rawTokenA);
    $this->service->captureSignature($signerA, [], $this->base64Png);
    $this->service->advanceSession($session->fresh());

    $this->service->validateToken($rawTokenB);
    $this->service->captureSignature($signerB, [], $this->base64Png);
    $this->service->advanceSession($session->fresh());

    $events = SigningAuditLog::where('signing_session_id', $session->id)
        ->orderBy('created_at')
        ->pluck('event')
        ->toArray();

    // Should contain: created, sent x2, viewed x2, signed x2, completed
    expect($events)->toContain('created');
    expect(collect($events)->filter(fn ($e) => $e === 'sent')->count())->toBe(2);
    expect(collect($events)->filter(fn ($e) => $e === 'viewed')->count())->toBe(2);
    expect(collect($events)->filter(fn ($e) => $e === 'signed')->count())->toBe(2);
    expect($events)->toContain('completed');
});
