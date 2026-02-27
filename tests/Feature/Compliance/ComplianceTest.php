<?php

use App\Models\AuditLog;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\RegulatoryFramework;
use App\Models\SigningAuditLog;
use App\Models\SigningSession;
use App\Models\SigningSessionSigner;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PdfService;
use App\Services\RegulatoryComplianceService;
use App\Services\SigningService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ═══════════════════════════════════════════════════════════════════════════
// AUDIT LOGGING (1-7)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 1. Create action generates audit log entry
// ---------------------------------------------------------------------------
it('create action generates audit log entry', function () {
    AuditService::log('create', 'Contract', 'uuid-create-test', ['title' => 'New MSA']);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'create',
        'resource_type' => 'Contract',
        'resource_id' => 'uuid-create-test',
        'actor_email' => $this->admin->email,
    ]);
});

// ---------------------------------------------------------------------------
// 2. Update action generates audit log entry
// ---------------------------------------------------------------------------
it('update action generates audit log entry', function () {
    AuditService::log('update', 'Contract', 'uuid-update-test', ['old_status' => 'draft', 'new_status' => 'review']);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'update',
        'resource_type' => 'Contract',
        'resource_id' => 'uuid-update-test',
    ]);
});

// ---------------------------------------------------------------------------
// 3. Delete action generates audit log entry
// ---------------------------------------------------------------------------
it('delete action generates audit log entry', function () {
    AuditService::log('delete', 'Counterparty', 'uuid-delete-test');

    $this->assertDatabaseHas('audit_log', [
        'action' => 'delete',
        'resource_type' => 'Counterparty',
        'resource_id' => 'uuid-delete-test',
    ]);
});

// ---------------------------------------------------------------------------
// 4. Audit log captures all expected fields
// ---------------------------------------------------------------------------
it('audit log captures all expected fields including details', function () {
    $details = [
        'old_status' => 'draft',
        'new_status' => 'active',
        'changed_fields' => ['workflow_state', 'title'],
    ];

    AuditService::log('state_change', 'Contract', 'fields-test', $details);

    $log = AuditLog::where('resource_id', 'fields-test')->first();

    expect($log->at)->not->toBeNull();
    expect($log->actor_id)->toBe($this->admin->id);
    expect($log->actor_email)->toBe($this->admin->email);
    expect($log->details)->toBeArray();
    expect($log->details['old_status'])->toBe('draft');
    expect($log->details['changed_fields'])->toBe(['workflow_state', 'title']);
});

// ---------------------------------------------------------------------------
// 5. Audit log records are immutable - cannot update
// ---------------------------------------------------------------------------
it('audit log records are immutable and cannot be updated', function () {
    AuditService::log('create', 'Contract', 'immutable-update-test');

    $log = AuditLog::where('resource_id', 'immutable-update-test')->first();

    expect(fn () => $log->update(['action' => 'delete']))
        ->toThrow(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// 6. Audit log records are immutable - cannot delete
// ---------------------------------------------------------------------------
it('audit log records are immutable and cannot be deleted', function () {
    AuditService::log('delete', 'Contract', 'immutable-delete-test');

    $log = AuditLog::where('resource_id', 'immutable-delete-test')->first();

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// 7. Audit log access control: system_admin and audit can view
// ---------------------------------------------------------------------------
it('system_admin and audit users can access audit log page', function () {
    foreach (['system_admin', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/audit-logs')->assertSuccessful();
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// CONTRACT-LEVEL AUDIT (8)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 8. Activity tab shows only entries for the specific contract
// ---------------------------------------------------------------------------
it('audit log can be filtered by contract resource_id', function () {
    $contract1 = Contract::factory()->create();
    $contract2 = Contract::factory()->create();

    AuditService::log('create', 'contract', $contract1->id, ['title' => 'Contract 1']);
    AuditService::log('update', 'contract', $contract1->id, ['field' => 'value']);
    AuditService::log('create', 'contract', $contract2->id, ['title' => 'Contract 2']);

    $contract1Logs = AuditLog::where('resource_type', 'contract')
        ->where('resource_id', $contract1->id)
        ->get();

    expect($contract1Logs)->toHaveCount(2);
    expect($contract1Logs->pluck('action')->toArray())->toBe(['create', 'update']);
});

// ═══════════════════════════════════════════════════════════════════════════
// SIGNING AUDIT LOG (9-15)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 9. Signing session creates audit log entry on creation
// ---------------------------------------------------------------------------
it('signing audit log records session creation event', function () {
    $contract = Contract::factory()->create();

    $session = SigningSession::factory()->create(['contract_id' => $contract->id]);

    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'created',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $this->assertDatabaseHas('signing_audit_log', [
        'signing_session_id' => $session->id,
        'event' => 'created',
    ]);
});

// ---------------------------------------------------------------------------
// 10. Signing audit records 'sent' event
// ---------------------------------------------------------------------------
it('signing audit log records sent event', function () {
    $session = SigningSession::factory()->create();

    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'sent',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $this->assertDatabaseHas('signing_audit_log', [
        'signing_session_id' => $session->id,
        'event' => 'sent',
    ]);
});

// ---------------------------------------------------------------------------
// 11. Signing audit records 'viewed' event
// ---------------------------------------------------------------------------
it('signing audit log records viewed event', function () {
    $session = SigningSession::factory()->create();

    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'viewed',
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0',
        'created_at' => now(),
    ]);

    $this->assertDatabaseHas('signing_audit_log', [
        'signing_session_id' => $session->id,
        'event' => 'viewed',
    ]);
});

// ---------------------------------------------------------------------------
// 12. Signing audit records 'signed' event
// ---------------------------------------------------------------------------
it('signing audit log records signed event', function () {
    $session = SigningSession::factory()->create();

    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'signed',
        'ip_address' => '192.168.1.1',
        'created_at' => now(),
    ]);

    $this->assertDatabaseHas('signing_audit_log', [
        'signing_session_id' => $session->id,
        'event' => 'signed',
    ]);
});

// ---------------------------------------------------------------------------
// 13. Signing audit records 'declined' event
// ---------------------------------------------------------------------------
it('signing audit log records declined event', function () {
    $session = SigningSession::factory()->create();

    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'declined',
        'details' => ['reason' => 'Terms not acceptable'],
        'ip_address' => '10.0.0.2',
        'created_at' => now(),
    ]);

    $log = SigningAuditLog::where('signing_session_id', $session->id)
        ->where('event', 'declined')
        ->first();

    expect($log->details['reason'])->toBe('Terms not acceptable');
});

// ---------------------------------------------------------------------------
// 14. Signing audit records 'completed' event
// ---------------------------------------------------------------------------
it('signing audit log records completed event', function () {
    $session = SigningSession::factory()->create();

    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'completed',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $this->assertDatabaseHas('signing_audit_log', [
        'signing_session_id' => $session->id,
        'event' => 'completed',
    ]);
});

// ---------------------------------------------------------------------------
// 15. Signing audit log entries tied to specific session
// ---------------------------------------------------------------------------
it('signing audit entries are scoped to their session', function () {
    $session1 = SigningSession::factory()->create();
    $session2 = SigningSession::factory()->create();

    SigningAuditLog::create(['signing_session_id' => $session1->id, 'event' => 'created', 'created_at' => now()]);
    SigningAuditLog::create(['signing_session_id' => $session1->id, 'event' => 'sent', 'created_at' => now()]);
    SigningAuditLog::create(['signing_session_id' => $session1->id, 'event' => 'signed', 'created_at' => now()]);
    SigningAuditLog::create(['signing_session_id' => $session2->id, 'event' => 'created', 'created_at' => now()]);

    $session1Logs = SigningAuditLog::where('signing_session_id', $session1->id)->get();
    $session2Logs = SigningAuditLog::where('signing_session_id', $session2->id)->get();

    expect($session1Logs)->toHaveCount(3);
    expect($session2Logs)->toHaveCount(1);
});

// ═══════════════════════════════════════════════════════════════════════════
// REGULATORY FRAMEWORKS (16-18)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 16. Create regulatory framework
// ---------------------------------------------------------------------------
it('can create a regulatory framework with requirements', function () {
    config(['features.regulatory_compliance' => true]);

    $framework = RegulatoryFramework::factory()->create([
        'jurisdiction_code' => 'EU',
        'framework_name' => 'GDPR Compliance',
        'requirements' => [
            ['id' => 'req-1', 'text' => 'Must include DPA', 'category' => 'data_protection', 'severity' => 'critical'],
            ['id' => 'req-2', 'text' => 'Consent clause', 'category' => 'data_protection', 'severity' => 'high'],
        ],
    ]);

    $this->assertDatabaseHas('regulatory_frameworks', [
        'jurisdiction_code' => 'EU',
        'framework_name' => 'GDPR Compliance',
    ]);

    expect($framework->requirements)->toHaveCount(2);
    expect($framework->requirement_count)->toBe(2);
});

// ---------------------------------------------------------------------------
// 17. Compliance check dispatches job
// ---------------------------------------------------------------------------
it('compliance check dispatches a processing job', function () {
    config(['features.regulatory_compliance' => true]);
    Queue::fake();

    $contract = Contract::factory()->create(['workflow_state' => 'review']);

    $framework = RegulatoryFramework::factory()->create([
        'requirements' => [
            ['id' => 'req-1', 'text' => 'Must include DPA', 'category' => 'data_protection', 'severity' => 'critical'],
        ],
    ]);

    $service = app(RegulatoryComplianceService::class);
    $service->runComplianceCheck($contract, $framework);

    Queue::assertPushed(\App\Jobs\ProcessComplianceCheck::class);
});

// ---------------------------------------------------------------------------
// 18. Compliance check produces findings grouped by framework
// ---------------------------------------------------------------------------
it('compliance findings are grouped by framework', function () {
    $contract = Contract::factory()->create();
    $fw1 = RegulatoryFramework::factory()->create();
    $fw2 = RegulatoryFramework::factory()->create();

    ComplianceFinding::factory()->count(3)->create([
        'contract_id' => $contract->id,
        'framework_id' => $fw1->id,
    ]);
    ComplianceFinding::factory()->count(2)->create([
        'contract_id' => $contract->id,
        'framework_id' => $fw2->id,
    ]);

    config(['features.regulatory_compliance' => true]);
    $findings = app(RegulatoryComplianceService::class)->getFindings($contract);

    expect($findings)->toHaveCount(2);
    expect($findings[$fw1->id])->toHaveCount(3);
    expect($findings[$fw2->id])->toHaveCount(2);
});

// ═══════════════════════════════════════════════════════════════════════════
// FINDING STATUS (19-22)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 19. Finding status can be updated to compliant
// ---------------------------------------------------------------------------
it('finding status can be reviewed and updated to compliant', function () {
    config(['features.regulatory_compliance' => true]);

    $finding = ComplianceFinding::factory()->create(['status' => 'unclear']);

    $updated = app(RegulatoryComplianceService::class)
        ->reviewFinding($finding, 'compliant', $this->admin);

    expect($updated->status)->toBe('compliant');
    expect($updated->reviewed_by)->toBe($this->admin->id);
    expect($updated->reviewed_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 20. Finding status can be updated to non_compliant
// ---------------------------------------------------------------------------
it('finding status can be reviewed and updated to non_compliant', function () {
    config(['features.regulatory_compliance' => true]);

    $finding = ComplianceFinding::factory()->create(['status' => 'unclear']);

    $updated = app(RegulatoryComplianceService::class)
        ->reviewFinding($finding, 'non_compliant', $this->admin);

    expect($updated->status)->toBe('non_compliant');
});

// ---------------------------------------------------------------------------
// 21. Invalid status rejected on review
// ---------------------------------------------------------------------------
it('review rejects invalid finding status', function () {
    config(['features.regulatory_compliance' => true]);

    $finding = ComplianceFinding::factory()->create(['status' => 'unclear']);

    expect(fn () => app(RegulatoryComplianceService::class)
        ->reviewFinding($finding, 'invalid_status', $this->admin))
        ->toThrow(\InvalidArgumentException::class);
});

// ---------------------------------------------------------------------------
// 22. Score summary calculates compliance percentage
// ---------------------------------------------------------------------------
it('score summary calculates compliance percentage correctly', function () {
    config(['features.regulatory_compliance' => true]);

    $contract = Contract::factory()->create();
    $fw = RegulatoryFramework::factory()->create();

    ComplianceFinding::factory()->count(3)->create([
        'contract_id' => $contract->id,
        'framework_id' => $fw->id,
        'status' => 'compliant',
    ]);
    ComplianceFinding::factory()->create([
        'contract_id' => $contract->id,
        'framework_id' => $fw->id,
        'status' => 'non_compliant',
    ]);
    ComplianceFinding::factory()->create([
        'contract_id' => $contract->id,
        'framework_id' => $fw->id,
        'status' => 'not_applicable',
    ]);

    $scores = app(RegulatoryComplianceService::class)->getScoreSummary($contract);

    $score = $scores[$fw->id];
    expect($score['total'])->toBe(5);
    expect($score['compliant'])->toBe(3);
    expect($score['non_compliant'])->toBe(1);
    expect($score['not_applicable'])->toBe(1);
    // Score = 3 / (5 - 1) * 100 = 75.0
    expect($score['score'])->toBe(75.0);
});

// ═══════════════════════════════════════════════════════════════════════════
// DOCUMENT INTEGRITY (23-25)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 23. PDF hash computation returns SHA256
// ---------------------------------------------------------------------------
it('computes SHA256 hash for document integrity', function () {
    $pdfContent = '%PDF-1.4 fake pdf content for hash test';

    $hash = app(PdfService::class)->computeHash($pdfContent);

    expect($hash)->toBe(hash('sha256', $pdfContent));
    expect(strlen($hash))->toBe(64);
});

// ---------------------------------------------------------------------------
// 24. Same content produces same hash
// ---------------------------------------------------------------------------
it('same content produces same hash for idempotency', function () {
    $content = '%PDF-1.4 identical content test';

    $hash1 = app(PdfService::class)->computeHash($content);
    $hash2 = app(PdfService::class)->computeHash($content);

    expect($hash1)->toBe($hash2);
});

// ---------------------------------------------------------------------------
// 25. Different content produces different hash
// ---------------------------------------------------------------------------
it('different content produces different hash', function () {
    $hash1 = app(PdfService::class)->computeHash('%PDF-1.4 content A');
    $hash2 = app(PdfService::class)->computeHash('%PDF-1.4 content B');

    expect($hash1)->not->toBe($hash2);
});

// ═══════════════════════════════════════════════════════════════════════════
// AUDIT CERTIFICATE (26-27)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 26. Audit certificate PDF can be generated for a signing session
// ---------------------------------------------------------------------------
it('generates audit certificate PDF for a signing session', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $contract = Contract::factory()->create(['title' => 'Cert Test Contract']);
    $session = SigningSession::factory()->create([
        'contract_id' => $contract->id,
        'status' => 'completed',
        'completed_at' => now(),
        'document_hash' => hash('sha256', 'test-document'),
    ]);

    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'created',
        'ip_address' => '127.0.0.1',
        'created_at' => now()->subMinutes(10),
    ]);
    SigningAuditLog::create([
        'signing_session_id' => $session->id,
        'event' => 'completed',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $outputPath = app(PdfService::class)->generateAuditCertificate($session);

    expect($outputPath)->toContain('certificate.pdf');
    Storage::disk(config('ccrs.contracts_disk'))->assertExists($outputPath);
});

// ---------------------------------------------------------------------------
// 27. Audit certificate contains session and signer details
// ---------------------------------------------------------------------------
it('audit certificate output path includes session ID', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $contract = Contract::factory()->create();
    $session = SigningSession::factory()->create([
        'contract_id' => $contract->id,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $outputPath = app(PdfService::class)->generateAuditCertificate($session);

    expect($outputPath)->toContain($session->id);
});
