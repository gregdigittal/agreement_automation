<?php

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
    Storage::fake(config('ccrs.contracts_disk'));
    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);

    $region = Region::create(['name' => 'Session Test Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Session Test Entity']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Session Test Project']);
    $cp = Counterparty::create(['legal_name' => 'Session Test CP', 'status' => 'Active']);

    Storage::disk(config('ccrs.contracts_disk'))->put('contracts/session-test.pdf', '%PDF-1.4 fake session test pdf');

    $this->contract = Contract::create([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial',
        'title' => 'Session Test Contract',
        'storage_path' => 'contracts/session-test.pdf',
        'file_name' => 'session-test.pdf',
    ]);

    $this->service = app(SigningService::class);
    $this->base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
});

// ── Session Creation ────────────────────────────────────────────────────

it('1. creates a signing session from a contract', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    expect($session->status)->toBe('active');
    expect($session->contract_id)->toBe($this->contract->id);
    expect($session->initiated_by)->toBe($this->user->id);
});

it('2. supports sequential signing order', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'internal', 'order' => 1],
    ], 'sequential');

    expect($session->signing_order)->toBe('sequential');
});

it('3. supports parallel signing order', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'external', 'order' => 1],
    ], 'parallel');

    expect($session->signing_order)->toBe('parallel');
});

it('4. creates signers with correct attributes', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'internal', 'order' => 1],
    ], 'sequential');

    expect($session->signers)->toHaveCount(2);

    $alice = $session->signers->where('signer_email', 'alice@example.com')->first();
    expect($alice->signer_name)->toBe('Alice');
    expect($alice->signer_type)->toBe('external');
    expect($alice->signing_order)->toBe(0);
    expect($alice->status)->toBe('pending');

    $bob = $session->signers->where('signer_email', 'bob@example.com')->first();
    expect($bob->signer_type)->toBe('internal');
    expect($bob->signing_order)->toBe(1);
});

it('5. session has 30-day validity', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    expect($session->expires_at)->not->toBeNull();
    // Should expire approximately 30 days from now
    $diffDays = (int) abs(now()->diffInDays($session->expires_at));
    expect($diffDays)->toBeGreaterThanOrEqual(29);
    expect($diffDays)->toBeLessThanOrEqual(31);
});

it('6. generates CSPRNG tokens with 7-day expiry', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    // Raw token is 64 hex chars (32 bytes CSPRNG)
    expect($rawToken)->toHaveLength(64);
    expect(ctype_xdigit($rawToken))->toBeTrue();

    $signer->refresh();
    // DB stores SHA-256 hash (64 hex chars)
    expect($signer->token)->toHaveLength(64);
    expect($signer->token)->toBe(hash('sha256', $rawToken));

    // Token expires in ~7 days
    $diffDays = (int) abs(now()->diffInDays($signer->token_expires_at));
    expect($diffDays)->toBeGreaterThanOrEqual(6);
    expect($diffDays)->toBeLessThanOrEqual(8);
});

// ── Sequential Signing ──────────────────────────────────────────────────

it('7. first signer gets email in sequential mode', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'external', 'order' => 1],
    ], 'sequential');

    $signer1 = $session->signers->sortBy('signing_order')->first();
    $this->service->sendToSigner($signer1);

    $signer1->refresh();
    expect($signer1->status)->toBe('sent');

    Mail::assertSent(SigningInvitation::class, function (SigningInvitation $mail) {
        return $mail->signer->signer_email === 'alice@example.com';
    });
});

it('8. after first signer completes, next signer gets invitation in sequential mode', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'external', 'order' => 1],
    ], 'sequential');

    // Send to and sign as first signer
    $signer1 = $session->signers->sortBy('signing_order')->first();
    $rawToken1 = $this->service->sendToSigner($signer1);
    $signer1->refresh();

    $this->service->validateToken($rawToken1);
    $this->service->captureSignature($signer1, [], $this->base64Png);

    // Reset mail to track new sends
    Mail::fake();

    // Advance should send to second signer
    $this->service->advanceSession($session->fresh());

    $signer2 = $session->signers->sortBy('signing_order')->skip(1)->first()->fresh();
    expect($signer2->status)->toBe('sent');
    expect($signer2->token)->not->toBeNull();

    Mail::assertSent(SigningInvitation::class, function (SigningInvitation $mail) {
        return $mail->signer->signer_email === 'bob@example.com';
    });
});

it('9. decline stops the signing chain in sequential mode', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Bob', 'email' => 'bob@example.com', 'type' => 'external', 'order' => 1],
    ], 'sequential');

    $signer1 = $session->signers->sortBy('signing_order')->first();
    $rawToken1 = $this->service->sendToSigner($signer1);
    $signer1->refresh();

    // Decline via controller
    $response = $this->post(route('signing.decline', ['token' => $rawToken1]), [
        'reason' => 'Terms unacceptable',
    ]);

    $response->assertStatus(200);
    expect($signer1->fresh()->status)->toBe('declined');
    expect($session->fresh()->status)->toBe('cancelled');

    // Second signer should never get sent
    $signer2 = $session->signers->sortBy('signing_order')->skip(1)->first();
    expect($signer2->status)->toBe('pending');
});

// ── Parallel Signing ────────────────────────────────────────────────────

it('10. all signers get email in parallel mode', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer B', 'email' => 'b@example.com', 'type' => 'external', 'order' => 1],
    ], 'parallel');

    $signerA = $session->signers->where('signer_email', 'a@example.com')->first();
    $signerB = $session->signers->where('signer_email', 'b@example.com')->first();

    $this->service->sendToSigner($signerA);
    $this->service->sendToSigner($signerB);

    Mail::assertSent(SigningInvitation::class, 2);

    expect($signerA->fresh()->status)->toBe('sent');
    expect($signerB->fresh()->status)->toBe('sent');
});

it('11. parallel signers can sign in any order', function () {
    // Mock PdfService for potential completion
    $mockPdf = Mockery::mock(PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/parallel-order.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'parallel-order'));
    app()->instance(PdfService::class, $mockPdf);
    Storage::disk(config('ccrs.contracts_disk'))->put('contracts/signed/parallel-order.pdf', '%PDF-sealed');

    $session = $this->service->createSession($this->contract, [
        ['name' => 'Signer A', 'email' => 'a@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer B', 'email' => 'b@example.com', 'type' => 'external', 'order' => 1],
    ], 'parallel');

    $signerA = $session->signers->where('signer_email', 'a@example.com')->first();
    $signerB = $session->signers->where('signer_email', 'b@example.com')->first();

    $rawTokenA = $this->service->sendToSigner($signerA);
    $rawTokenB = $this->service->sendToSigner($signerB);

    // Signer B signs first
    $this->service->validateToken($rawTokenB);
    $this->service->captureSignature($signerB, [], $this->base64Png);
    expect($signerB->fresh()->status)->toBe('signed');

    // Then Signer A signs
    $this->service->validateToken($rawTokenA);
    $this->service->captureSignature($signerA, [], $this->base64Png);
    expect($signerA->fresh()->status)->toBe('signed');

    // Advance — should complete
    $this->service->advanceSession($session->fresh());
    expect($session->fresh()->status)->toBe('completed');
});

it('12. parallel session auto-completes when all sign', function () {
    $mockPdf = Mockery::mock(PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/parallel-auto.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'parallel-auto'));
    app()->instance(PdfService::class, $mockPdf);
    Storage::disk(config('ccrs.contracts_disk'))->put('contracts/signed/parallel-auto.pdf', '%PDF-sealed');

    $session = $this->service->createSession($this->contract, [
        ['name' => 'X', 'email' => 'x@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Y', 'email' => 'y@example.com', 'type' => 'external', 'order' => 1],
        ['name' => 'Z', 'email' => 'z@example.com', 'type' => 'external', 'order' => 2],
    ], 'parallel');

    $tokens = [];
    foreach ($session->signers as $signer) {
        $tokens[$signer->signer_email] = $this->service->sendToSigner($signer);
    }

    // All three sign
    foreach (['x@example.com', 'y@example.com', 'z@example.com'] as $email) {
        $signer = $session->signers->where('signer_email', $email)->first();
        $this->service->validateToken($tokens[$email]);
        $this->service->captureSignature($signer, [], $this->base64Png);
    }

    $this->service->advanceSession($session->fresh());
    expect($session->fresh()->status)->toBe('completed');
    expect($session->fresh()->completed_at)->not->toBeNull();
});

// ── External Signer Magic Link ──────────────────────────────────────────

it('13. valid magic link shows signing page', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'External', 'email' => 'ext@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    $response = $this->get(route('signing.show', ['token' => $rawToken]));

    $response->assertStatus(200);
    $response->assertViewIs('signing.show');
    $response->assertViewHas('signer');
    $response->assertViewHas('session');
    $response->assertViewHas('contract');
});

it('14. expired token returns 403', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'External', 'email' => 'ext@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    // Manually expire the token
    $signer->update(['token_expires_at' => now()->subDay()]);

    $response = $this->get(route('signing.show', ['token' => $rawToken]));
    $response->assertStatus(403);
});

it('15. used token cannot be reused after signing', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'External', 'email' => 'ext@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $rawToken = $this->service->sendToSigner($signer);

    // Sign
    $this->service->validateToken($rawToken);
    $this->service->captureSignature($signer, [], $this->base64Png);
    expect($signer->fresh()->status)->toBe('signed');

    // Attempt to reuse token
    expect(fn () => $this->service->validateToken($rawToken))
        ->toThrow(\RuntimeException::class, 'You have already signed this document.');
});

// ── Page Enforcement ────────────────────────────────────────────────────

it('16. require_all_pages_viewed can be set on session', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential', ['require_all_pages_viewed' => true]);

    expect($session->require_all_pages_viewed)->toBeTrue();
    $this->assertDatabaseHas('signing_sessions', [
        'id' => $session->id,
        'require_all_pages_viewed' => true,
    ]);
});

it('17. require_page_initials can be set on session', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential', ['require_page_initials' => true]);

    expect($session->require_page_initials)->toBeTrue();
    $this->assertDatabaseHas('signing_sessions', [
        'id' => $session->id,
        'require_page_initials' => true,
    ]);
});

it('18. enforcement flags default to false when not specified', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $session = $session->fresh();
    expect($session->require_all_pages_viewed)->toBeFalse();
    expect($session->require_page_initials)->toBeFalse();
});
