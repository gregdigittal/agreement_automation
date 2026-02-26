<?php

namespace Tests\Feature;

use App\Jobs\ProcessContractBatch;
use App\Models\BulkUpload;
use App\Models\BulkUploadRow;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessContractBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_row_and_creates_contract(): void
    {
        Storage::fake(config('ccrs.contracts_disk'));

        $region = Region::factory()->create();
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $project = Project::factory()->create(['entity_id' => $entity->id]);
        $counterparty = Counterparty::factory()->create();

        $bulkUpload = BulkUpload::create([
            'id' => Str::uuid()->toString(),
            'csv_filename' => 'test.csv',
            'total_rows' => 1,
            'status' => 'processing',
        ]);

        $rowId = Str::uuid()->toString();
        BulkUploadRow::create([
            'id' => $rowId,
            'bulk_upload_id' => $bulkUpload->id,
            'row_number' => 1,
            'row_data' => [
                'title' => 'Test Contract',
                'contract_type' => 'Commercial',
                'region_code' => $region->code,
                'entity_code' => $entity->code,
                'project_code' => $project->code,
                'counterparty_registration' => $counterparty->registration_number,
                'file_path' => 'doc.pdf',
            ],
            'status' => 'pending',
        ]);

        Storage::disk(config('ccrs.contracts_disk'))->put('bulk_uploads/files/doc.pdf', 'fake pdf content');

        ProcessContractBatch::dispatchSync($rowId);

        $row = BulkUploadRow::findOrFail($rowId);
        $this->assertSame('completed', $row->status);
        $this->assertNotNull($row->contract_id);

        $contract = Contract::findOrFail($row->contract_id);
        $this->assertSame('Test Contract', $contract->title);
        $this->assertSame('Commercial', $contract->contract_type);
    }
}
