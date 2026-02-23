<?php

namespace Tests\Feature;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharePointIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_stores_sharepoint_url_and_version(): void
    {
        $contract = Contract::factory()->create();

        $contract->update([
            'sharepoint_url'     => 'https://digittalgroup.sharepoint.com/sites/legal/document.docx',
            'sharepoint_version' => '3.1',
        ]);

        $contract->refresh();
        $this->assertStringContainsString('sharepoint.com', $contract->sharepoint_url);
        $this->assertEquals('3.1', $contract->sharepoint_version);
    }
}
