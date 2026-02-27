<?php

use App\Jobs\ProcessContractBatch;
use App\Models\BulkUpload;
use App\Models\BulkUploadRow;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\BulkDataImportService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Helper: write a CSV string to a temp file and return the path.
 */
function writeBulkOpsTempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($path, $content);
    return $path;
}

// ═══════════════════════════════════════════════════════════════════════════
// ACCESS CONTROL (1-3)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 1. system_admin can access bulk data upload page
// ---------------------------------------------------------------------------
it('system_admin can access bulk data upload page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $this->get('/admin/bulk-data-upload-page')->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 2. system_admin can access bulk contract upload page
// ---------------------------------------------------------------------------
it('system_admin can access bulk contract upload page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $this->get('/admin/bulk-contract-upload-page')->assertSuccessful();
});

// ---------------------------------------------------------------------------
// 3. Non-system_admin roles cannot access either bulk page
// ---------------------------------------------------------------------------
it('non-admin roles cannot access bulk upload pages', function () {
    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/bulk-data-upload-page')->assertForbidden();
        $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// BULK DATA IMPORT (4-10)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 4. CSV import creates region records
// ---------------------------------------------------------------------------
it('CSV import creates region records', function () {
    $csv = "name,code\nMiddle East,MENA\nEurope,EUR\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('regions', $path);

    expect($result['success'])->toBe(2);
    expect($result['failed'])->toBe(0);
    $this->assertDatabaseHas('regions', ['name' => 'Middle East', 'code' => 'MENA']);
    $this->assertDatabaseHas('regions', ['name' => 'Europe', 'code' => 'EUR']);

    unlink($path);
});

// ---------------------------------------------------------------------------
// 5. Dependency order: entities require existing regions
// ---------------------------------------------------------------------------
it('entity import requires existing region by code', function () {
    Region::create(['name' => 'MENA Region', 'code' => 'MENA']);

    $csv = "region_code,name,code,legal_name,registration_number\nMENA,Digittal AE,DGT-AE,Digittal AE LLC,REG-001\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('entities', $path);

    expect($result['success'])->toBe(1);
    expect($result['failed'])->toBe(0);
    $this->assertDatabaseHas('entities', ['name' => 'Digittal AE', 'code' => 'DGT-AE']);

    unlink($path);
});

// ---------------------------------------------------------------------------
// 6. Entity import fails with invalid region code
// ---------------------------------------------------------------------------
it('entity import fails when region code does not exist', function () {
    $csv = "region_code,name\nNONEXIST,Bad Entity\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('entities', $path);

    expect($result['success'])->toBe(0);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain("Region with code 'NONEXIST' not found");

    unlink($path);
});

// ---------------------------------------------------------------------------
// 7. Duplicate users are skipped on re-import
// ---------------------------------------------------------------------------
it('does not duplicate existing users on re-import', function () {
    User::factory()->create(['email' => 'existing@digittal.io', 'name' => 'Original']);

    $csv = "name,email,role\nUpdated Name,existing@digittal.io,finance\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('users', $path);

    expect($result['success'])->toBe(1);
    expect(User::where('email', 'existing@digittal.io')->count())->toBe(1);
    expect(User::where('email', 'existing@digittal.io')->first()->name)->toBe('Original');

    unlink($path);
});

// ---------------------------------------------------------------------------
// 8. Template generation for each resource type
// ---------------------------------------------------------------------------
it('generates CSV template headers for each resource type', function () {
    $service = app(BulkDataImportService::class);

    expect($service->generateTemplate('regions'))->toBe("name,code\n");
    expect($service->generateTemplate('entities'))->toBe("region_code,name,code,legal_name,registration_number\n");
    expect($service->generateTemplate('projects'))->toBe("entity_code,name,code\n");
    expect($service->generateTemplate('users'))->toBe("name,email,role\n");
    expect($service->generateTemplate('counterparties'))->toBe("legal_name,registration_number,address,jurisdiction,status\n");
});

// ---------------------------------------------------------------------------
// 9. Rejects CSV with missing required columns
// ---------------------------------------------------------------------------
it('rejects CSV with missing required columns', function () {
    $csv = "wrong_column\nsome data\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('regions', $path);

    expect($result['success'])->toBe(0);
    expect($result['errors'][0])->toContain('Missing required columns: name');

    unlink($path);
});

// ---------------------------------------------------------------------------
// 10. Import results include success and failure counts
// ---------------------------------------------------------------------------
it('import results include mixed success and failure counts', function () {
    Region::create(['name' => 'Existing Region', 'code' => 'EX']);

    $csv = "region_code,name\nEX,Good Entity\nBAD,Bad Entity\nEX,Another Good\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('entities', $path);

    expect($result['success'])->toBe(2);
    expect($result['failed'])->toBe(1);

    unlink($path);
});

// ═══════════════════════════════════════════════════════════════════════════
// BULK CONTRACT UPLOAD (11-21)
// ═══════════════════════════════════════════════════════════════════════════

// ---------------------------------------------------------------------------
// 11. ProcessContractBatch creates a contract from row data
// ---------------------------------------------------------------------------
it('processes a bulk upload row and creates a contract', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $region = Region::factory()->create();
    $entity = Entity::factory()->create(['region_id' => $region->id]);
    $project = Project::factory()->create(['entity_id' => $entity->id]);
    $counterparty = Counterparty::factory()->create(['registration_number' => 'REG-BULK-001']);

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
            'title' => 'Bulk Test Contract',
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
    expect($row->status)->toBe('completed');
    expect($row->contract_id)->not->toBeNull();

    $contract = Contract::findOrFail($row->contract_id);
    expect($contract->title)->toBe('Bulk Test Contract');
    expect($contract->contract_type)->toBe('Commercial');
});

// ---------------------------------------------------------------------------
// 12. Bulk upload status tracking: BulkUpload record created
// ---------------------------------------------------------------------------
it('creates a BulkUpload record to track status', function () {
    $bulkUpload = BulkUpload::create([
        'id' => Str::uuid()->toString(),
        'csv_filename' => 'contracts.csv',
        'total_rows' => 5,
        'status' => 'processing',
    ]);

    $this->assertDatabaseHas('bulk_uploads', [
        'id' => $bulkUpload->id,
        'status' => 'processing',
        'total_rows' => 5,
    ]);
});

// ---------------------------------------------------------------------------
// 13. Bulk upload rows are tracked individually
// ---------------------------------------------------------------------------
it('tracks individual row status in BulkUploadRow', function () {
    $bulkUpload = BulkUpload::create([
        'id' => Str::uuid()->toString(),
        'csv_filename' => 'test.csv',
        'total_rows' => 2,
        'status' => 'processing',
    ]);

    $row1 = BulkUploadRow::create([
        'id' => Str::uuid()->toString(),
        'bulk_upload_id' => $bulkUpload->id,
        'row_number' => 1,
        'row_data' => ['title' => 'Contract A'],
        'status' => 'completed',
    ]);

    $row2 = BulkUploadRow::create([
        'id' => Str::uuid()->toString(),
        'bulk_upload_id' => $bulkUpload->id,
        'row_number' => 2,
        'row_data' => ['title' => 'Contract B'],
        'status' => 'failed',
        'error' => 'Missing region code',
    ]);

    expect($row1->status)->toBe('completed');
    expect($row2->status)->toBe('failed');
    expect($row2->error)->toBe('Missing region code');
});

// ---------------------------------------------------------------------------
// 14. Validation errors are captured in row data
// ---------------------------------------------------------------------------
it('captures validation errors in row error field', function () {
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
            'title' => 'Bad Contract',
            'contract_type' => 'Commercial',
            'region_code' => 'NONEXISTENT',
            'entity_code' => 'NOPE',
            'project_code' => 'BAD',
            'counterparty_registration' => 'INVALID',
            'file_path' => 'missing.pdf',
        ],
        'status' => 'pending',
    ]);

    try {
        ProcessContractBatch::dispatchSync($rowId);
    } catch (\Throwable) {
        // Job re-throws after marking row as failed
    }

    $row = BulkUploadRow::findOrFail($rowId);
    expect($row->status)->toBe('failed');
    expect($row->error)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// 15. Async processing dispatches jobs to queue
// ---------------------------------------------------------------------------
it('dispatches ProcessContractBatch jobs to queue', function () {
    Queue::fake();

    $rowId = Str::uuid()->toString();
    ProcessContractBatch::dispatch($rowId);

    Queue::assertPushed(ProcessContractBatch::class);
});

// ---------------------------------------------------------------------------
// 16. BulkUpload has relationship to rows
// ---------------------------------------------------------------------------
it('BulkUpload has many BulkUploadRows', function () {
    $bulkUpload = BulkUpload::create([
        'id' => Str::uuid()->toString(),
        'csv_filename' => 'rel-test.csv',
        'total_rows' => 3,
        'status' => 'processing',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        BulkUploadRow::create([
            'id' => Str::uuid()->toString(),
            'bulk_upload_id' => $bulkUpload->id,
            'row_number' => $i,
            'row_data' => ['title' => "Contract $i"],
            'status' => 'pending',
        ]);
    }

    expect($bulkUpload->rows)->toHaveCount(3);
});

// ---------------------------------------------------------------------------
// 17. Page shows upload form content
// ---------------------------------------------------------------------------
it('bulk data upload page shows upload form content', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $response = $this->get('/admin/bulk-data-upload-page');
    $response->assertSuccessful();
    $response->assertSee('Bulk Data Upload');
});

// ---------------------------------------------------------------------------
// 18. User import assigns roles correctly
// ---------------------------------------------------------------------------
it('user import assigns roles correctly', function () {
    $csv = "name,email,role\nJohn Doe,john@digittal.io,legal\nJane Smith,jane@digittal.io,commercial\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('users', $path);

    expect($result['success'])->toBe(2);

    $john = User::where('email', 'john@digittal.io')->first();
    expect($john->hasRole('legal'))->toBeTrue();

    $jane = User::where('email', 'jane@digittal.io')->first();
    expect($jane->hasRole('commercial'))->toBeTrue();

    unlink($path);
});

// ---------------------------------------------------------------------------
// 19. Invalid role is rejected on user import
// ---------------------------------------------------------------------------
it('rejects user import with invalid role', function () {
    $csv = "name,email,role\nBad Role,bad@test.com,superadmin\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('users', $path);

    expect($result['success'])->toBe(0);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain("Invalid role");

    unlink($path);
});

// ---------------------------------------------------------------------------
// 20. Counterparty import from CSV
// ---------------------------------------------------------------------------
it('imports counterparties from CSV with all fields', function () {
    $csv = "legal_name,registration_number,address,jurisdiction,status\nAcme Corp,REG-123,123 Main St,AE,Active\n";
    $path = writeBulkOpsTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('counterparties', $path);

    expect($result['success'])->toBe(1);
    $this->assertDatabaseHas('counterparties', [
        'legal_name' => 'Acme Corp',
        'registration_number' => 'REG-123',
        'jurisdiction' => 'AE',
    ]);

    unlink($path);
});

// ---------------------------------------------------------------------------
// 21. Empty CSV is rejected
// ---------------------------------------------------------------------------
it('rejects empty CSV file', function () {
    $path = writeBulkOpsTempCsv('');

    $result = app(BulkDataImportService::class)->import('regions', $path);

    expect($result['success'])->toBe(0);
    expect($result['errors'][0])->toContain('empty or has no headers');

    unlink($path);
});
