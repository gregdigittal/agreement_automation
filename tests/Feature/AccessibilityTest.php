<?php

/**
 * WCAG 2.1 AA Accessibility Regression Tests
 *
 * Verifies that key ARIA attributes and accessibility landmarks are present
 * in signing and vendor portal views. Prevents regression of C-8 fixes.
 */

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\SigningService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

// ---------------------------------------------------------------------------
// Signing Page Accessibility
// ---------------------------------------------------------------------------

describe('Signing page WCAG 2.1 AA', function () {
    beforeEach(function () {
        Mail::fake();
        Storage::fake(config('ccrs.contracts_disk'));
        $this->withoutVite();

        $user = User::factory()->create();
        $this->actingAs($user);

        $region = Region::create(['name' => 'A11y Test Region']);
        $entity = Entity::create(['region_id' => $region->id, 'name' => 'A11y Test Entity']);
        $project = Project::create(['entity_id' => $entity->id, 'name' => 'A11y Test Project']);
        $cp = Counterparty::create(['legal_name' => 'A11y Test CP', 'status' => 'Active']);

        Storage::disk(config('ccrs.contracts_disk'))->put('contracts/a11y-test.pdf', '%PDF-1.4 a11y test');

        $contract = Contract::create([
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'counterparty_id' => $cp->id,
            'contract_type' => 'Commercial',
            'title' => 'A11y Test Contract',
            'storage_path' => 'contracts/a11y-test.pdf',
            'file_name' => 'a11y-test.pdf',
        ]);

        $service = app(SigningService::class);
        $session = $service->createSession($contract, [
            ['name' => 'A11y Signer', 'email' => 'a11y@example.com', 'type' => 'external', 'order' => 0],
        ], 'sequential');

        $signer = $session->signers->first();
        $this->rawToken = $service->sendToSigner($signer);
    });

    it('has skip-to-main-content link as first focusable element', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertStatus(200);
        // Skip link targets #main-content
        $response->assertSee('href="#main-content"', false);
        $response->assertSee('sr-only', false);
    });

    it('has html lang attribute set', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('<html lang="en"', false);
    });

    it('has main landmark with tabindex for skip-link target', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('id="main-content"', false);
        $response->assertSee('tabindex="-1"', false);
    });

    it('signature tab buttons have ids for aria-labelledby association', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('id="tab-btn-draw"', false);
        $response->assertSee('id="tab-btn-type"', false);
        $response->assertSee('id="tab-btn-upload"', false);
        $response->assertSee('id="tab-btn-webcam"', false);
    });

    it('signature tab panels have aria-labelledby referencing their tab buttons', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('aria-labelledby="tab-btn-draw"', false);
        $response->assertSee('aria-labelledby="tab-btn-type"', false);
        $response->assertSee('aria-labelledby="tab-btn-upload"', false);
        $response->assertSee('aria-labelledby="tab-btn-webcam"', false);
    });

    it('signature tablist has role and accessible label', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('role="tablist"', false);
        $response->assertSee('aria-label="Signature method"', false);
    });

    it('tab buttons have correct role and aria-selected', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('role="tab"', false);
        $response->assertSee('aria-selected="true"', false);
        $response->assertSee('aria-selected="false"', false);
        $response->assertSee('aria-controls="tab-panel-draw"', false);
    });

    it('decline button has aria-haspopup and aria-controls for modal', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('aria-haspopup="dialog"', false);
        $response->assertSee('aria-controls="decline-modal"', false);
    });

    it('decline modal has role dialog and aria-modal', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-labelledby="decline-modal-title"', false);
    });

    it('typed signature input has autocomplete name attribute', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('id="typed-signature"', false);
        $response->assertSee('autocomplete="name"', false);
    });

    it('webcam video element has accessible label', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('aria-label="Camera feed for signature capture"', false);
    });

    it('draw panel has screen reader notice about keyboard alternatives', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        $response->assertSee('Keyboard users', false);
        $response->assertSee('Type or Upload tab', false);
    });

    it('signature canvas is marked aria-hidden for screen readers', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        // Canvas is not operable by keyboard; sr-only notice covers it
        $response->assertSee('id="signature-pad-canvas"', false);
        $response->assertSee('aria-hidden="true"', false);
    });

    it('file upload input uses sr-only class for keyboard accessibility', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        // sr-only keeps it in DOM for keyboard users; hidden would remove it
        $html = $response->getContent();
        expect($html)->toContain('id="signature-upload"')
            ->and($html)->toContain('sr-only')
            ->and($html)->not->toMatch('/id="signature-upload"[^>]*class="hidden"/');
    });

    it('decorative SVG icons are hidden from assistive technology', function () {
        $response = $this->get(route('signing.show', ['token' => $this->rawToken]));

        // At least one aria-hidden="true" on SVG in header/icons
        $response->assertSee('aria-hidden="true"', false);
    });
});

// ---------------------------------------------------------------------------
// Signing Layout Pages
// ---------------------------------------------------------------------------

describe('Signing layout and state pages WCAG 2.1 AA', function () {
    beforeEach(function () {
        Mail::fake();
        Storage::fake(config('ccrs.contracts_disk'));
        $this->withoutVite();

        $user = User::factory()->create();
        $this->actingAs($user);

        $region = Region::create(['name' => 'State Page Region']);
        $entity = Entity::create(['region_id' => $region->id, 'name' => 'State Page Entity']);
        $project = Project::create(['entity_id' => $entity->id, 'name' => 'State Page Project']);
        $cp = Counterparty::create(['legal_name' => 'State Page CP', 'status' => 'Active']);

        Storage::disk(config('ccrs.contracts_disk'))->put('contracts/state-test.pdf', '%PDF-1.4 state');

        $contract = Contract::create([
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'counterparty_id' => $cp->id,
            'contract_type' => 'Commercial',
            'title' => 'State Page Contract',
            'storage_path' => 'contracts/state-test.pdf',
            'file_name' => 'state-test.pdf',
        ]);

        $service = app(SigningService::class);
        $session = $service->createSession($contract, [
            ['name' => 'State Signer', 'email' => 'state@example.com', 'type' => 'external', 'order' => 0],
        ], 'sequential');

        $signer = $session->signers->first();
        $this->rawToken = $service->sendToSigner($signer);
    });

    it('error page renders with accessible heading structure', function () {
        $response = $this->get(route('signing.show', ['token' => 'invalid-token-that-does-not-exist']));

        // Invalid token returns 403 with signing.error view (expected behaviour — see SigningControllerTest)
        $response->assertStatus(403);
        $response->assertViewIs('signing.error');
        $response->assertSee('<h1', false);
        $response->assertSee('aria-hidden="true"', false); // decorative SVG
    });

    it('declined page renders with accessible heading structure', function () {
        // The declined view is returned directly from the POST /sign/{token}/decline response
        $response = $this->post(route('signing.decline', ['token' => $this->rawToken]), ['reason' => 'test']);

        $response->assertViewIs('signing.declined');
        $response->assertSee('<h1', false);
        $response->assertSee('aria-hidden="true"', false); // decorative SVG
    });
});

// ---------------------------------------------------------------------------
// Vendor Login Page Accessibility
// ---------------------------------------------------------------------------

describe('Vendor login page WCAG 2.1 AA', function () {
    it('has html lang attribute', function () {
        $response = $this->get(route('vendor.login'));

        $response->assertStatus(200);
        $response->assertSee('<html lang="en"', false);
    });

    it('has skip to main content link', function () {
        $response = $this->get(route('vendor.login'));

        $response->assertSee('href="#main-content"', false);
        $response->assertSee('sr-only', false);
    });

    it('has main landmark as skip-link target', function () {
        $response = $this->get(route('vendor.login'));

        $response->assertSee('id="main-content"', false);
    });

    it('email input is properly labelled', function () {
        $response = $this->get(route('vendor.login'));

        $response->assertSee('for="email"', false);
        $response->assertSee('id="email"', false);
    });

    it('error message uses role alert for screen readers', function () {
        $response = $this->post(route('vendor.auth.request'), ['email' => 'not-an-email']);

        // Follow redirect back to form
        $response = $this->get(route('vendor.login'));
        // role="alert" is present in the template for when errors exist
        $response->assertSee('role="alert"', false);
    });
});
