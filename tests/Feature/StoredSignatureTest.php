<?php

use App\Models\Counterparty;
use App\Models\StoredSignature;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ── Migration & schema tests ────────────────────────────────────────────

it('can create a stored signature for a user', function () {
    $sig = StoredSignature::create([
        'user_id' => $this->user->id,
        'label' => 'My formal signature',
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig.png',
        'is_default' => true,
    ]);

    $this->assertDatabaseHas('stored_signatures', [
        'id' => $sig->id,
        'user_id' => $this->user->id,
        'type' => 'signature',
        'is_default' => true,
    ]);
});

it('can create a stored signature for a counterparty', function () {
    $cp = Counterparty::create(['legal_name' => 'Acme Corp', 'status' => 'Active']);

    $sig = StoredSignature::create([
        'counterparty_id' => $cp->id,
        'signer_email' => 'contact@acme.com',
        'label' => 'Acme signature',
        'type' => 'signature',
        'capture_method' => 'upload',
        'image_path' => 'stored-signatures/cp/sig.png',
    ]);

    $this->assertDatabaseHas('stored_signatures', [
        'counterparty_id' => $cp->id,
        'signer_email' => 'contact@acme.com',
    ]);
});

it('can create an initials record', function () {
    $sig = StoredSignature::create([
        'user_id' => $this->user->id,
        'label' => 'My initials',
        'type' => 'initials',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/initials.png',
    ]);

    expect($sig->type)->toBe('initials');
});

it('supports webcam capture method', function () {
    $sig = StoredSignature::create([
        'user_id' => $this->user->id,
        'label' => 'Camera capture',
        'type' => 'signature',
        'capture_method' => 'webcam',
        'image_path' => 'stored-signatures/test/webcam.png',
    ]);

    expect($sig->capture_method)->toBe('webcam');
});

it('casts is_default to boolean', function () {
    $sig = StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig.png',
        'is_default' => 1,
    ]);

    expect($sig->is_default)->toBeBool()->toBeTrue();
});

// ── Relationship tests ──────────────────────────────────────────────────

it('user has storedSignatures relationship', function () {
    StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig1.png',
    ]);

    StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'initials',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/init1.png',
    ]);

    expect($this->user->storedSignatures)->toHaveCount(2);
});

it('counterparty has storedSignatures relationship', function () {
    $cp = Counterparty::create(['legal_name' => 'Test Co', 'status' => 'Active']);

    StoredSignature::create([
        'counterparty_id' => $cp->id,
        'type' => 'signature',
        'capture_method' => 'upload',
        'image_path' => 'stored-signatures/cp/sig.png',
    ]);

    expect($cp->storedSignatures)->toHaveCount(1);
});

it('stored signature belongs to user', function () {
    $sig = StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig.png',
    ]);

    expect($sig->user)->not->toBeNull();
    expect($sig->user->id)->toBe($this->user->id);
});

it('stored signature belongs to counterparty', function () {
    $cp = Counterparty::create(['legal_name' => 'CP Test', 'status' => 'Active']);

    $sig = StoredSignature::create([
        'counterparty_id' => $cp->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/cp/sig.png',
    ]);

    expect($sig->counterparty)->not->toBeNull();
    expect($sig->counterparty->id)->toBe($cp->id);
});

it('cascades delete when user is deleted', function () {
    $sig = StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig.png',
    ]);

    $sigId = $sig->id;
    $this->assertDatabaseHas('stored_signatures', ['id' => $sigId]);

    $this->user->delete();

    $this->assertDatabaseMissing('stored_signatures', ['id' => $sigId]);
});

it('cascades delete when counterparty is deleted', function () {
    $cp = Counterparty::create(['legal_name' => 'Del Corp', 'status' => 'Active']);

    $sig = StoredSignature::create([
        'counterparty_id' => $cp->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/cp/sig.png',
    ]);

    $sigId = $sig->id;
    $cp->delete();

    $this->assertDatabaseMissing('stored_signatures', ['id' => $sigId]);
});

// ── Scope tests ─────────────────────────────────────────────────────────

it('forSigner scope finds by user id', function () {
    StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig.png',
    ]);

    $other = User::factory()->create();
    StoredSignature::create([
        'user_id' => $other->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/other/sig.png',
    ]);

    $results = StoredSignature::forSigner($this->user->id, null)->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->user_id)->toBe($this->user->id);
});

it('forSigner scope finds by email', function () {
    StoredSignature::create([
        'signer_email' => 'signer@example.com',
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/email/sig.png',
    ]);

    StoredSignature::create([
        'signer_email' => 'other@example.com',
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/other/sig.png',
    ]);

    $results = StoredSignature::forSigner(null, 'signer@example.com')->get();
    expect($results)->toHaveCount(1);
    expect($results->first()->signer_email)->toBe('signer@example.com');
});

it('forSigner scope finds by user id or email', function () {
    StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig1.png',
    ]);

    StoredSignature::create([
        'signer_email' => $this->user->email,
        'type' => 'initials',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/init1.png',
    ]);

    $results = StoredSignature::forSigner($this->user->id, $this->user->email)->get();
    expect($results)->toHaveCount(2);
});

// ── Default management tests ────────────────────────────────────────────

it('can have only one default per type per user', function () {
    $sig1 = StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig1.png',
        'is_default' => true,
    ]);

    // Manually clear default and set new one (mimics page logic)
    StoredSignature::where('user_id', $this->user->id)
        ->where('type', 'signature')
        ->update(['is_default' => false]);

    $sig2 = StoredSignature::create([
        'user_id' => $this->user->id,
        'type' => 'signature',
        'capture_method' => 'draw',
        'image_path' => 'stored-signatures/test/sig2.png',
        'is_default' => true,
    ]);

    expect($sig1->fresh()->is_default)->toBeFalse();
    expect($sig2->fresh()->is_default)->toBeTrue();
});

// ── My Signatures Page tests ────────────────────────────────────────────

it('my signatures page is accessible to all authenticated users', function () {
    foreach (['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/my-signatures-page')->assertSuccessful();
    }
});

it('my signatures page shows stored signatures heading', function () {
    $response = $this->get('/admin/my-signatures-page');
    $response->assertSuccessful();
    $response->assertSee('Your Stored Signatures');
});
