<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\SigningService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('s3');
    $this->withoutVite();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $region = Region::create(['name' => 'Ctrl Test Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Ctrl Test Entity']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Ctrl Test Project']);
    $cp = Counterparty::create(['legal_name' => 'Ctrl Test CP', 'status' => 'Active']);

    Storage::disk('s3')->put('contracts/ctrl-test.pdf', '%PDF-1.4 fake controller test pdf');

    $this->contract = Contract::create([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial',
        'title' => 'Controller Test Contract',
        'storage_path' => 'contracts/ctrl-test.pdf',
        'file_name' => 'ctrl-test.pdf',
    ]);

    $this->service = app(SigningService::class);

    // Create session and send to signer to get a valid raw token
    $this->session = $this->service->createSession($this->contract, [
        ['name' => 'HTTP Signer', 'email' => 'httpsigner@example.com', 'type' => 'external', 'order' => 0],
    ], 'sequential');

    $this->signer = $this->session->signers->first();
    $this->rawToken = $this->service->sendToSigner($this->signer);
    $this->signer->refresh();
});

it('shows signing page for valid token via GET /sign/{token}', function () {
    $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

    $response->assertStatus(200);
    $response->assertViewIs('signing.show');
    $response->assertViewHas('signer');
    $response->assertViewHas('session');
    $response->assertViewHas('contract');
    $response->assertViewHas('rawToken', $this->rawToken);
});

it('returns 403 for invalid token via GET /sign/{token}', function () {
    $response = $this->get(route('signing.show', ['token' => 'invalid-token-that-does-not-exist']));

    $response->assertStatus(403);
    $response->assertViewIs('signing.error');
});

it('returns 403 for expired token via GET /sign/{token}', function () {
    $this->signer->update(['token_expires_at' => now()->subDay()]);

    $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

    $response->assertStatus(403);
    $response->assertViewIs('signing.error');
});

it('submits signature via POST /sign/{token}/submit', function () {
    // First view the signing page (sets viewed_at)
    $this->get(route('signing.show', ['token' => $this->rawToken]));

    $base64Png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    // Mock PdfService for completion
    $mockPdf = Mockery::mock(\App\Services\PdfService::class);
    $mockPdf->shouldReceive('getPageCount')->andReturn(1);
    $mockPdf->shouldReceive('overlaySignatures')->andReturn('contracts/signed/ctrl-final.pdf');
    $mockPdf->shouldReceive('generateAuditCertificate')->andReturn('contracts/audit/cert.pdf');
    $mockPdf->shouldReceive('computeHash')->andReturn(hash('sha256', 'ctrl-test'));
    app()->instance(\App\Services\PdfService::class, $mockPdf);
    Storage::disk('s3')->put('contracts/signed/ctrl-final.pdf', '%PDF-sealed');

    $response = $this->post(route('signing.submit', ['token' => $this->rawToken]), [
        'signature_image' => $base64Png,
        'signature_method' => 'draw',
        'fields' => [],
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('signing.complete');

    $this->signer->refresh();
    expect($this->signer->status)->toBe('signed');
});

it('returns 403 for invalid token on submit', function () {
    $response = $this->post(route('signing.submit', ['token' => 'bogus-token']), [
        'signature_image' => 'data',
        'signature_method' => 'draw',
    ]);

    $response->assertStatus(403);
});

it('validates required fields on submit', function () {
    $response = $this->post(route('signing.submit', ['token' => $this->rawToken]), []);

    $response->assertSessionHasErrors(['signature_image', 'signature_method']);
});

it('declines signing via POST /sign/{token}/decline', function () {
    $response = $this->post(route('signing.decline', ['token' => $this->rawToken]), [
        'reason' => 'Terms are unacceptable',
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('signing.declined');

    $this->signer->refresh();
    expect($this->signer->status)->toBe('declined');
    expect($this->session->fresh()->status)->toBe('cancelled');
});

it('returns 403 for invalid token on decline', function () {
    $response = $this->post(route('signing.decline', ['token' => 'bogus-token']), [
        'reason' => 'test',
    ]);

    $response->assertStatus(403);
});

it('serves document PDF via GET /sign/{token}/document', function () {
    $response = $this->get(route('signing.document', ['token' => $this->rawToken]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('returns 403 for invalid token on document download', function () {
    $response = $this->get(route('signing.document', ['token' => 'bogus-token']));

    $response->assertStatus(403);
});

it('returns 403 when session is cancelled', function () {
    $this->session->update(['status' => 'cancelled']);

    $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

    $response->assertStatus(403);
});
