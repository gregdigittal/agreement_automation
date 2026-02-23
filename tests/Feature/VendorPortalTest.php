<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\VendorDocument;
use App\Models\VendorNotification;
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
        Storage::fake('s3');
        $counterparty = Counterparty::factory()->create();
        $vendor = VendorUser::factory()->create(['counterparty_id' => $counterparty->id]);
        $contract = Contract::factory()->create(['counterparty_id' => $counterparty->id]);

        $this->actingAs($vendor, 'vendor');

        $doc = VendorDocument::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'counterparty_id' => $counterparty->id,
            'title' => 'Test Doc',
            'contract_id' => $contract->id,
            'filename' => 'test.pdf',
            'storage_path' => 'vendor_documents/' . $counterparty->id . '/test.pdf',
            'document_type' => 'supporting',
            'uploaded_by_vendor_user_id' => $vendor->id,
        ]);

        $this->assertEquals($counterparty->id, $doc->counterparty_id);
        $this->assertEquals($vendor->id, $doc->uploaded_by_vendor_user_id);
    }

    public function test_vendor_cannot_see_another_counterparty_contracts(): void
    {
        $cp1 = Counterparty::factory()->create();
        $cp2 = Counterparty::factory()->create();
        $vendor1 = VendorUser::factory()->create(['counterparty_id' => $cp1->id]);
        Contract::factory()->create(['counterparty_id' => $cp2->id, 'title' => 'Other CP Contract']);

        $contractsForVendor = Contract::where('counterparty_id', $vendor1->counterparty_id)->get();
        $this->assertCount(0, $contractsForVendor->where('title', 'Other CP Contract'));
    }

    public function test_notifications_created_on_contract_status_change(): void
    {
        $counterparty = Counterparty::factory()->create();
        $vendor = VendorUser::factory()->create(['counterparty_id' => $counterparty->id]);
        $contract = Contract::factory()->create(['counterparty_id' => $counterparty->id,
            'title' => 'Test Doc', 'title' => 'Test Agreement']);

        app(VendorNotificationService::class)->notifyContractStatusChange($contract, 'executed');

        $this->assertDatabaseHas('vendor_notifications', [
            'vendor_user_id' => $vendor->id,
            'subject' => 'Agreement Status Update: Test Agreement',
        ]);
    }
}
