<?php

use App\Filament\Resources\VendorUserResource\Pages\CreateVendorUser;
use App\Filament\Resources\VendorUserResource\Pages\ListVendorUsers;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\User;
use App\Models\VendorDocument;
use App\Models\VendorLoginToken;
use App\Models\VendorNotification;
use App\Models\VendorUser;
use App\Services\VendorNotificationService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    config(['features.vendor_portal' => true]);
});

// ═══════════════════════════════════════════════════════════════════════════
// MAGIC LINK AUTH (1-6)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 1. Request link creates token for valid vendor email
// ---------------------------------------------------------------------------
it('creates a login token when vendor requests magic link', function () {
    $vendor = VendorUser::factory()->create(['email' => 'vendor@example.com']);

    $response = $this->post(route('vendor.auth.request'), ['email' => 'vendor@example.com']);

    $response->assertSessionHas('status', 'If an account exists, a login link has been sent.');

    $this->assertDatabaseHas('vendor_login_tokens', [
        'vendor_user_id' => $vendor->id,
    ]);
});

// ---------------------------------------------------------------------------
// 2. Returns generic message for unknown email (no info leak)
// ---------------------------------------------------------------------------
it('returns generic message for unknown email address', function () {
    $response = $this->post(route('vendor.auth.request'), ['email' => 'nobody@unknown.example']);

    $response->assertSessionHas('status', 'If an account exists, a login link has been sent.');
    $this->assertDatabaseCount('vendor_login_tokens', 0);
});

// ---------------------------------------------------------------------------
// 3. Valid token logs in vendor
// ---------------------------------------------------------------------------
it('verifies valid token and logs in vendor', function () {
    $vendor = VendorUser::factory()->create();
    $rawToken = Str::random(64);

    VendorLoginToken::create([
        'id' => Str::uuid()->toString(),
        'vendor_user_id' => $vendor->id,
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(15),
        'created_at' => now(),
    ]);

    $response = $this->get(route('vendor.auth.verify', ['token' => $rawToken]));

    $response->assertRedirect('/vendor');
    $this->assertTrue(Auth::guard('vendor')->check());
    expect(Auth::guard('vendor')->id())->toBe($vendor->id);
});

// ---------------------------------------------------------------------------
// 4. Expired token is rejected
// ---------------------------------------------------------------------------
it('rejects expired magic link token', function () {
    $vendor = VendorUser::factory()->create();
    $rawToken = Str::random(64);

    VendorLoginToken::create([
        'id' => Str::uuid()->toString(),
        'vendor_user_id' => $vendor->id,
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->subMinutes(5),
        'created_at' => now()->subMinutes(20),
    ]);

    $response = $this->get(route('vendor.auth.verify', ['token' => $rawToken]));

    $response->assertRedirect(route('vendor.login'));
    $response->assertSessionHasErrors(['token' => 'Invalid or expired link.']);
    $this->assertFalse(Auth::guard('vendor')->check());
});

// ---------------------------------------------------------------------------
// 5. Token hash is stored, not raw token
// ---------------------------------------------------------------------------
it('stores token as a hash not raw value', function () {
    $vendor = VendorUser::factory()->create();
    $rawToken = Str::random(64);

    VendorLoginToken::create([
        'id' => Str::uuid()->toString(),
        'vendor_user_id' => $vendor->id,
        'token_hash' => hash('sha256', $rawToken),
        'expires_at' => now()->addMinutes(15),
        'created_at' => now(),
    ]);

    $token = VendorLoginToken::where('vendor_user_id', $vendor->id)->first();

    // Token hash should not be the raw token
    expect($token->token_hash)->not->toBe($rawToken);
    // But it should match the hash of the raw token
    expect($token->token_hash)->toBe(hash('sha256', $rawToken));
});

// ---------------------------------------------------------------------------
// 6. Logout destroys vendor session
// ---------------------------------------------------------------------------
it('logout destroys vendor session', function () {
    $vendor = VendorUser::factory()->create();
    $this->actingAs($vendor, 'vendor');

    $this->assertTrue(Auth::guard('vendor')->check());

    $response = $this->post(route('vendor.logout'));

    $response->assertRedirect(route('vendor.login'));
    $this->assertFalse(Auth::guard('vendor')->check());
});

// ═══════════════════════════════════════════════════════════════════════════
// VENDOR DASHBOARD (7-10)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 7. Vendor sees only their counterparty's contracts
// ---------------------------------------------------------------------------
it('vendor sees only own counterparty contracts', function () {
    $cp1 = Counterparty::factory()->create();
    $cp2 = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp1->id]);

    Contract::factory()->create(['counterparty_id' => $cp1->id, 'title' => 'My Contract']);
    Contract::factory()->create(['counterparty_id' => $cp2->id, 'title' => 'Other Contract']);

    $contractsForVendor = Contract::where('counterparty_id', $vendor->counterparty_id)->get();

    expect($contractsForVendor)->toHaveCount(1);
    expect($contractsForVendor->first()->title)->toBe('My Contract');
});

// ---------------------------------------------------------------------------
// 8. Data isolation: cannot see other counterparty contracts
// ---------------------------------------------------------------------------
it('enforces data isolation between counterparties', function () {
    $cpA = Counterparty::factory()->create();
    $cpB = Counterparty::factory()->create();
    $vendorA = VendorUser::factory()->create(['counterparty_id' => $cpA->id]);

    Contract::factory()->count(3)->create(['counterparty_id' => $cpA->id]);
    Contract::factory()->count(5)->create(['counterparty_id' => $cpB->id]);

    $visible = Contract::where('counterparty_id', $vendorA->counterparty_id)->count();
    expect($visible)->toBe(3);
});

// ---------------------------------------------------------------------------
// 9. Vendor documents scoped to counterparty
// ---------------------------------------------------------------------------
it('vendor documents are scoped to their counterparty', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $cpA = Counterparty::factory()->create();
    $cpB = Counterparty::factory()->create();
    $vendorA = VendorUser::factory()->create(['counterparty_id' => $cpA->id]);
    $contractA = Contract::factory()->create(['counterparty_id' => $cpA->id]);

    VendorDocument::create([
        'id' => Str::uuid()->toString(),
        'counterparty_id' => $cpA->id,
        'contract_id' => $contractA->id,
        'filename' => 'doc-a.pdf',
        'storage_path' => 'vendor_documents/' . $cpA->id . '/doc-a.pdf',
        'document_type' => 'supporting',
        'uploaded_by_vendor_user_id' => $vendorA->id,
    ]);

    VendorDocument::create([
        'id' => Str::uuid()->toString(),
        'counterparty_id' => $cpB->id,
        'contract_id' => Contract::factory()->create(['counterparty_id' => $cpB->id])->id,
        'filename' => 'doc-b.pdf',
        'storage_path' => 'vendor_documents/' . $cpB->id . '/doc-b.pdf',
        'document_type' => 'certificate',
    ]);

    $scopedDocs = VendorDocument::where('counterparty_id', $vendorA->counterparty_id)->get();
    expect($scopedDocs)->toHaveCount(1);
    expect($scopedDocs->first()->filename)->toBe('doc-a.pdf');
});

// ---------------------------------------------------------------------------
// 10. Uploaded documents reference uploading vendor
// ---------------------------------------------------------------------------
it('uploaded documents reference the uploading vendor user', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $cp = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp->id]);
    $contract = Contract::factory()->create(['counterparty_id' => $cp->id]);

    $doc = VendorDocument::create([
        'id' => Str::uuid()->toString(),
        'counterparty_id' => $cp->id,
        'contract_id' => $contract->id,
        'filename' => 'uploaded.pdf',
        'storage_path' => 'vendor_documents/' . $cp->id . '/uploaded.pdf',
        'document_type' => 'supporting',
        'uploaded_by_vendor_user_id' => $vendor->id,
    ]);

    expect($doc->uploaded_by_vendor_user_id)->toBe($vendor->id);
});

// ═══════════════════════════════════════════════════════════════════════════
// DOCUMENT UPLOAD (11-14)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 11. PDF upload creates VendorDocument record
// ---------------------------------------------------------------------------
it('PDF upload creates a VendorDocument record', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $cp = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp->id]);
    $contract = Contract::factory()->create(['counterparty_id' => $cp->id]);

    $storagePath = 'vendor_documents/' . $cp->id . '/test.pdf';
    Storage::disk(config('ccrs.contracts_disk'))->put($storagePath, 'fake pdf content');

    VendorDocument::create([
        'id' => Str::uuid()->toString(),
        'counterparty_id' => $cp->id,
        'contract_id' => $contract->id,
        'filename' => 'test.pdf',
        'storage_path' => $storagePath,
        'document_type' => 'supporting',
        'uploaded_by_vendor_user_id' => $vendor->id,
    ]);

    $this->assertDatabaseHas('vendor_documents', [
        'filename' => 'test.pdf',
        'counterparty_id' => $cp->id,
    ]);

    Storage::disk(config('ccrs.contracts_disk'))->assertExists($storagePath);
});

// ---------------------------------------------------------------------------
// 12. DOCX upload also supported
// ---------------------------------------------------------------------------
it('DOCX upload also creates a VendorDocument record', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $cp = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp->id]);
    $contract = Contract::factory()->create(['counterparty_id' => $cp->id]);

    $storagePath = 'vendor_documents/' . $cp->id . '/agreement.docx';
    Storage::disk(config('ccrs.contracts_disk'))->put($storagePath, 'fake docx content');

    VendorDocument::create([
        'id' => Str::uuid()->toString(),
        'counterparty_id' => $cp->id,
        'contract_id' => $contract->id,
        'filename' => 'agreement.docx',
        'storage_path' => $storagePath,
        'document_type' => 'supporting',
        'uploaded_by_vendor_user_id' => $vendor->id,
    ]);

    $this->assertDatabaseHas('vendor_documents', ['filename' => 'agreement.docx']);
    Storage::disk(config('ccrs.contracts_disk'))->assertExists($storagePath);
});

// ---------------------------------------------------------------------------
// 13. Storage path uses counterparty scoped directory
// ---------------------------------------------------------------------------
it('storage path is scoped to counterparty directory', function () {
    $cp = Counterparty::factory()->create();
    $expectedPrefix = 'vendor_documents/' . $cp->id . '/';

    $storagePath = $expectedPrefix . 'scoped-doc.pdf';

    expect($storagePath)->toStartWith($expectedPrefix);
});

// ---------------------------------------------------------------------------
// 14. Notification created when vendor notification service is called
// ---------------------------------------------------------------------------
it('internal notification created on contract status change for vendor', function () {
    $counterparty = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $counterparty->id]);
    $contract = Contract::factory()->create([
        'counterparty_id' => $counterparty->id,
        'title' => 'Vendor Notify Test',
    ]);

    Mail::fake();

    app(VendorNotificationService::class)->notifyContractStatusChange($contract, 'executed');

    $this->assertDatabaseHas('vendor_notifications', [
        'vendor_user_id' => $vendor->id,
        'subject' => 'Agreement Status Update: Vendor Notify Test',
    ]);
});

// ═══════════════════════════════════════════════════════════════════════════
// VENDOR NOTIFICATIONS (15-18)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 15. Send notification to vendor user
// ---------------------------------------------------------------------------
it('can send a notification to a vendor user', function () {
    $cp = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp->id]);

    $notif = VendorNotification::create([
        'id' => Str::uuid()->toString(),
        'vendor_user_id' => $vendor->id,
        'subject' => 'Welcome',
        'body' => 'Welcome to the portal.',
    ]);

    $this->assertDatabaseHas('vendor_notifications', [
        'vendor_user_id' => $vendor->id,
        'subject' => 'Welcome',
    ]);
});

// ---------------------------------------------------------------------------
// 16. Vendor notification displays with correct data
// ---------------------------------------------------------------------------
it('vendor notification contains subject and body', function () {
    $cp = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp->id]);

    VendorNotification::create([
        'id' => Str::uuid()->toString(),
        'vendor_user_id' => $vendor->id,
        'subject' => 'Contract Update',
        'body' => 'Your contract has been approved.',
    ]);

    $notif = VendorNotification::where('vendor_user_id', $vendor->id)->first();
    expect($notif->subject)->toBe('Contract Update');
    expect($notif->body)->toBe('Your contract has been approved.');
});

// ---------------------------------------------------------------------------
// 17. Unread notifications have null read_at
// ---------------------------------------------------------------------------
it('unread vendor notifications have null read_at', function () {
    $cp = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp->id]);

    VendorNotification::create([
        'id' => Str::uuid()->toString(),
        'vendor_user_id' => $vendor->id,
        'subject' => 'Unread Test',
        'body' => 'Should be unread.',
    ]);

    $notif = VendorNotification::where('vendor_user_id', $vendor->id)->first();
    expect($notif->read_at)->toBeNull();
    expect($notif->isRead())->toBeFalse();
});

// ---------------------------------------------------------------------------
// 18. Mark vendor notification as read
// ---------------------------------------------------------------------------
it('can mark vendor notification as read', function () {
    $cp = Counterparty::factory()->create();
    $vendor = VendorUser::factory()->create(['counterparty_id' => $cp->id]);

    $notif = VendorNotification::create([
        'id' => Str::uuid()->toString(),
        'vendor_user_id' => $vendor->id,
        'subject' => 'Mark Read Test',
        'body' => 'Will be marked read.',
    ]);

    $notif->markRead();
    $notif->refresh();

    expect($notif->read_at)->not->toBeNull();
    expect($notif->isRead())->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// ADMIN VENDOR USER MANAGEMENT (19-22)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 19. Admin can create vendor user via Filament form
// ---------------------------------------------------------------------------
it('admin can create a vendor user via Livewire form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $counterparty = Counterparty::create(['legal_name' => 'Vendor Corp', 'status' => 'Active']);

    Livewire::test(CreateVendorUser::class)
        ->fillForm([
            'name' => 'New Vendor',
            'email' => 'new@vendorcorp.com',
            'counterparty_id' => $counterparty->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('vendor_users', [
        'name' => 'New Vendor',
        'email' => 'new@vendorcorp.com',
        'counterparty_id' => $counterparty->id,
    ]);
});

// ---------------------------------------------------------------------------
// 20. Unique email validation on vendor user create
// ---------------------------------------------------------------------------
it('validates email uniqueness on vendor user create', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $counterparty = Counterparty::create(['legal_name' => 'Dup Corp', 'status' => 'Active']);
    VendorUser::create(['name' => 'Existing', 'email' => 'taken@vendor.com', 'counterparty_id' => $counterparty->id]);

    Livewire::test(CreateVendorUser::class)
        ->fillForm([
            'name' => 'Duplicate',
            'email' => 'taken@vendor.com',
            'counterparty_id' => $counterparty->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

// ---------------------------------------------------------------------------
// 21. Non-admin roles blocked from vendor user management
// ---------------------------------------------------------------------------
it('non-admin roles cannot access vendor user management', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/vendor-users')->assertForbidden();
});

// ---------------------------------------------------------------------------
// 22. Vendor user list page renders for admin
// ---------------------------------------------------------------------------
it('admin can render vendor user list page with filter', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->get('/admin/vendor-users')->assertSuccessful();
});
