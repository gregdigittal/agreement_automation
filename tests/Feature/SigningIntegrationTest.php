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

it('completes full signing flow with sequential signers', function () {
    Mail::fake();
    Storage::fake('s3');

    $user = User::factory()->create();
    $this->actingAs($user);

    $region = Region::create(['name' => 'Integration Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Integration Entity']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Integration Project']);
    $cp = Counterparty::create(['legal_name' => 'Integration CP', 'status' => 'Active']);

    // Upload a fake PDF so ContractFileService::download works
    Storage::disk('s3')->put('contracts/integration-test.pdf', '%PDF-1.4 fake pdf for integration test');

    $contract = Contract::create([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial',
        'title' => 'Integration Signing Test',
        'storage_path' => 'contracts/integration-test.pdf',
        'file_name' => 'integration-test.pdf',
    ]);

    // Mock PdfService so FPDI does not attempt to parse the fake PDF
    $mockPdf = Mockery::mock(PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/integration-final.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'integration-sealed'));
    app()->instance(PdfService::class, $mockPdf);

    Storage::disk('s3')->put('contracts/signed/integration-final.pdf', '%PDF-sealed-content');

    $service = app(SigningService::class);

    // 1. Create session with 2 sequential signers
    $session = $service->createSession($contract, [
        ['name' => 'Signer One', 'email' => 'one@example.com', 'type' => 'external', 'order' => 0],
        ['name' => 'Signer Two', 'email' => 'two@example.com', 'type' => 'external', 'order' => 1],
    ], 'sequential');

    expect($session->status)->toBe('active');
    expect($session->signers)->toHaveCount(2);

    // 2. Send to first signer
    $service->sendToSigner($session->signers->first());
    Mail::assertSent(SigningInvitation::class);

    // 3. First signer views and signs
    $signer1 = $session->signers->first()->fresh();
    $validatedSigner = $service->validateToken($signer1->token);
    expect($validatedSigner->viewed_at)->not->toBeNull();

    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $service->captureSignature($signer1, [], $base64Png);
    expect($signer1->fresh()->status)->toBe('signed');

    // 4. Advance sends to second signer
    $service->advanceSession($session->fresh());

    // 5. Second signer signs
    $signer2 = $session->signers->skip(1)->first()->fresh();
    expect($signer2->token)->not->toBeNull();
    expect($signer2->status)->toBe('sent');

    // Validate and capture second signer
    $service->validateToken($signer2->token);
    $service->captureSignature($signer2, [], $base64Png);
    expect($signer2->fresh()->status)->toBe('signed');

    // 6. Advance completes the session
    $service->advanceSession($session->fresh());

    // 7. Verify completion
    $session->refresh();
    expect($session->status)->toBe('completed');
    expect($contract->fresh()->signing_status)->toBe('signed');

    // 8. Verify audit log has entries
    $auditEntries = SigningAuditLog::where('signing_session_id', $session->id)->get();
    expect($auditEntries)->not->toBeEmpty();

    // Should have: created, sent (signer1), viewed (signer1), signed (signer1), sent (signer2), viewed (signer2), signed (signer2), completed
    expect($auditEntries->pluck('event')->toArray())
        ->toContain('created')
        ->toContain('sent')
        ->toContain('signed')
        ->toContain('completed');

    // Verify completion emails sent
    Mail::assertSent(SigningComplete::class);
});
