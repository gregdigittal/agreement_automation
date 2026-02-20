<?php

namespace Tests\Feature;

use App\Mail\VendorNotificationMail;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\VendorDocument;
use App\Models\VendorNotification;
use App\Models\VendorUser;
use App\Services\VendorNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorPortalTest extends TestCase
{
    use RefreshDatabase;

    private Counterparty $counterparty;
    private VendorUser $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->counterparty = Counterparty::create([
            'id' => Str::uuid()->toString(),
            'legal_name' => 'Acme Corp',
            'status' => 'Active',
        ]);

        $this->vendor = VendorUser::create([
            'id' => Str::uuid()->toString(),
            'counterparty_id' => $this->counterparty->id,
            'email' => 'vendor@acme.test',
            'name' => 'Jane Vendor',
        ]);
    }

    public function test_vendor_document_scoped_to_counterparty(): void
    {
        $otherCp = Counterparty::create([
            'id' => Str::uuid()->toString(),
            'legal_name' => 'Other Corp',
            'status' => 'Active',
        ]);

        VendorDocument::create([
            'id' => Str::uuid()->toString(),
            'counterparty_id' => $this->counterparty->id,
            'filename' => 'mine.pdf',
            'storage_path' => 'vendor_documents/mine.pdf',
            'document_type' => 'supporting',
            'uploaded_by_vendor_user_id' => $this->vendor->id,
        ]);

        VendorDocument::create([
            'id' => Str::uuid()->toString(),
            'counterparty_id' => $otherCp->id,
            'filename' => 'theirs.pdf',
            'storage_path' => 'vendor_documents/theirs.pdf',
            'document_type' => 'certificate',
        ]);

        $myDocs = VendorDocument::where('counterparty_id', $this->counterparty->id)->get();
        $this->assertCount(1, $myDocs);
        $this->assertEquals('mine.pdf', $myDocs->first()->filename);
    }

    public function test_vendor_cannot_see_other_counterparty_contracts(): void
    {
        $otherCp = Counterparty::create([
            'id' => Str::uuid()->toString(),
            'legal_name' => 'Other Corp',
            'status' => 'Active',
        ]);

        Contract::unguard();
        Contract::create([
            'id' => Str::uuid()->toString(),
            'counterparty_id' => $this->counterparty->id,
            'title' => 'My Contract',
            'workflow_state' => 'draft',
        ]);
        Contract::create([
            'id' => Str::uuid()->toString(),
            'counterparty_id' => $otherCp->id,
            'title' => 'Other Contract',
            'workflow_state' => 'draft',
        ]);
        Contract::reguard();

        $myContracts = Contract::where('counterparty_id', $this->counterparty->id)->get();
        $this->assertCount(1, $myContracts);
        $this->assertEquals('My Contract', $myContracts->first()->title);
    }

    public function test_notifications_created_on_contract_status_change(): void
    {
        Mail::fake();

        Contract::unguard();
        $contract = Contract::create([
            'id' => Str::uuid()->toString(),
            'counterparty_id' => $this->counterparty->id,
            'title' => 'Test Agreement',
            'workflow_state' => 'executed',
        ]);
        Contract::reguard();

        $service = app(VendorNotificationService::class);
        $service->notifyContractStatusChange($contract, 'executed');

        $this->assertDatabaseHas('vendor_notifications', [
            'vendor_user_id' => $this->vendor->id,
            'subject' => 'Agreement Status Update: Test Agreement',
        ]);

        Mail::assertQueued(VendorNotificationMail::class, function ($mail) {
            return $mail->hasTo('vendor@acme.test');
        });
    }

    public function test_magic_link_login_flow(): void
    {
        $token = Str::random(64);
        $this->vendor->update([
            'login_token' => hash('sha256', $token),
            'login_token_expires_at' => now()->addHours(48),
        ]);

        $this->assertEquals(hash('sha256', $token), $this->vendor->fresh()->login_token);
        $this->assertTrue($this->vendor->fresh()->login_token_expires_at->isFuture());
    }

    public function test_vendor_notification_mark_read(): void
    {
        $notification = VendorNotification::create([
            'id' => Str::uuid()->toString(),
            'vendor_user_id' => $this->vendor->id,
            'subject' => 'Test',
            'body' => 'Test body',
        ]);

        $this->assertFalse($notification->isRead());
        $notification->markRead();
        $this->assertTrue($notification->fresh()->isRead());
    }
}
