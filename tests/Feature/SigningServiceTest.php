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
    $this->service->sendToSigner($signer);

    $signer->refresh();
    expect($signer->token)->toHaveLength(64);
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
    $this->service->sendToSigner($signer);
    $signer->refresh();

    $validatedSigner = $this->service->validateToken($signer->token);

    expect($validatedSigner->id)->toBe($signer->id);
    expect($validatedSigner->fresh()->viewed_at)->not->toBeNull();
});

it('rejects expired tokens', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $this->service->sendToSigner($signer);

    // Manually expire the token
    $signer->update(['token_expires_at' => now()->subDay()]);

    $this->service->validateToken($signer->token);
})->throws(\RuntimeException::class, 'This signing link has expired.');

it('captures signature and updates signer', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $this->service->sendToSigner($signer);
    $signer->refresh();

    // Validate token first (sets viewed_at)
    $this->service->validateToken($signer->token);

    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $this->service->captureSignature($signer, [], $base64Png);

    $signer->refresh();
    expect($signer->status)->toBe('signed');
    expect($signer->signature_image_path)->not->toBeNull();
    expect($signer->signed_at)->not->toBeNull();

    // Verify signature image was stored
    Storage::disk('s3')->assertExists($signer->signature_image_path);
});

it('completes session when all signers done', function () {
    $session = $this->service->createSession($this->contract, [
        ['name' => 'Alice', 'email' => 'alice@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $signer = $session->signers->first();
    $this->service->sendToSigner($signer);
    $signer->refresh();

    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $this->service->captureSignature($signer, [], $base64Png);
    $this->service->advanceSession($session->fresh());

    $session->refresh();
    expect($session->status)->toBe('completed');
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
    $this->service->sendToSigner($signer1);
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
