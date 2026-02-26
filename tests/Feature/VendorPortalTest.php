<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\VendorDocument;
use App\Models\VendorUser;
use App\Services\VendorNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_upload_scoped_to_counterparty(): void
    {
        Storage::fake(config('ccrs.contracts_disk'));
        $counterpartyA = Counterparty::factory()->create();
        $counterpartyB = Counterparty::factory()->create();
        $vendorA = VendorUser::factory()->create(['counterparty_id' => $counterpartyA->id]);
        $contractA = Contract::factory()->create(['counterparty_id' => $counterpartyA->id]);
        $contractB = Contract::factory()->create(['counterparty_id' => $counterpartyB->id]);

        VendorDocument::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'counterparty_id' => $counterpartyA->id,
            'contract_id' => $contractA->id,
            'filename' => 'doc-a.pdf',
            'storage_path' => 'vendor_documents/' . $counterpartyA->id . '/doc-a.pdf',
            'document_type' => 'supporting',
            'uploaded_by_vendor_user_id' => $vendorA->id,
        ]);
        VendorDocument::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'counterparty_id' => $counterpartyB->id,
            'contract_id' => $contractB->id,
            'filename' => 'doc-b.pdf',
            'storage_path' => 'vendor_documents/' . $counterpartyB->id . '/doc-b.pdf',
            'document_type' => 'certificate',
            'uploaded_by_vendor_user_id' => null,
        ]);

        $scopedDocs = VendorDocument::where('counterparty_id', $vendorA->counterparty_id)->get();
        $this->assertCount(1, $scopedDocs);
        $this->assertEquals('doc-a.pdf', $scopedDocs->first()->filename);
    }

    public function test_vendor_cannot_see_another_counterparty_contracts(): void
    {
        $cp1 = Counterparty::factory()->create();
        $cp2 = Counterparty::factory()->create();
        $vendor1 = VendorUser::factory()->create(['counterparty_id' => $cp1->id]);
        Contract::factory()->create(['counterparty_id' => $cp1->id, 'title' => 'My Contract']);
        Contract::factory()->create(['counterparty_id' => $cp2->id, 'title' => 'Other CP Contract']);

        $contractsForVendor = Contract::where('counterparty_id', $vendor1->counterparty_id)->get();
        $this->assertCount(1, $contractsForVendor);
        $this->assertEquals('My Contract', $contractsForVendor->first()->title);
    }

    public function test_notifications_created_on_contract_status_change(): void
    {
        $counterparty = Counterparty::factory()->create();
        $vendor = VendorUser::factory()->create(['counterparty_id' => $counterparty->id]);
        $contract = Contract::factory()->create(['counterparty_id' => $counterparty->id, 'title' => 'Test Agreement']);

        app(VendorNotificationService::class)->notifyContractStatusChange($contract, 'executed');

        $this->assertDatabaseHas('vendor_notifications', [
            'vendor_user_id' => $vendor->id,
            'subject' => 'Agreement Status Update: Test Agreement',
        ]);
    }
}
