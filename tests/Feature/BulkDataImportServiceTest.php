<?php

use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\BulkDataImportService;

function writeTempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($path, $content);
    return $path;
}

// ── Region import ────────────────────────────────────────────────────────

it('imports regions from CSV', function () {
    $csv = "name,code\nMiddle East,MENA\nEurope,EUR\n";
    $path = writeTempCsv($csv);

    $service = app(BulkDataImportService::class);
    $result = $service->import('regions', $path);

    expect($result['success'])->toBe(2);
    expect($result['failed'])->toBe(0);
    expect($result['errors'])->toBeEmpty();

    $this->assertDatabaseHas('regions', ['name' => 'Middle East', 'code' => 'MENA']);
    $this->assertDatabaseHas('regions', ['name' => 'Europe', 'code' => 'EUR']);

    unlink($path);
});

it('imports regions with only required columns', function () {
    $csv = "name\nAsia Pacific\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('regions', $path);

    expect($result['success'])->toBe(1);
    $this->assertDatabaseHas('regions', ['name' => 'Asia Pacific']);

    unlink($path);
});

// ── Entity import ────────────────────────────────────────────────────────

it('imports entities from CSV with region lookup', function () {
    Region::create(['name' => 'MENA Region', 'code' => 'MENA']);

    $csv = "region_code,name,code,legal_name,registration_number\nMENA,Digittal AE,DGT-AE,Digittal AE LLC,REG-001\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('entities', $path);

    expect($result['success'])->toBe(1);
    expect($result['failed'])->toBe(0);
    $this->assertDatabaseHas('entities', ['name' => 'Digittal AE', 'code' => 'DGT-AE']);

    unlink($path);
});

it('fails entity import with invalid region code', function () {
    $csv = "region_code,name\nNONEXIST,Bad Entity\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('entities', $path);

    expect($result['success'])->toBe(0);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain("Region with code 'NONEXIST' not found");

    unlink($path);
});

// ── Project import ───────────────────────────────────────────────────────

it('imports projects from CSV with entity lookup', function () {
    $region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    Entity::create(['region_id' => $region->id, 'name' => 'Test Entity', 'code' => 'TE']);

    $csv = "entity_code,name,code\nTE,Project Alpha,PA\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('projects', $path);

    expect($result['success'])->toBe(1);
    $this->assertDatabaseHas('projects', ['name' => 'Project Alpha', 'code' => 'PA']);

    unlink($path);
});

it('fails project import with invalid entity code', function () {
    $csv = "entity_code,name\nBADCODE,Orphan Project\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('projects', $path);

    expect($result['success'])->toBe(0);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain("Entity with code 'BADCODE' not found");

    unlink($path);
});

// ── User import ──────────────────────────────────────────────────────────

it('imports users from CSV and assigns roles', function () {
    Illuminate\Support\Facades\Mail::fake();
    $csv = "name,email,roles\nJohn Doe,john@digittal.io,legal\nJane Smith,jane@digittal.io,commercial\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('users', $path);

    expect($result['success'])->toBe(2);
    expect($result['failed'])->toBe(0);

    $john = User::where('email', 'john@digittal.io')->first();
    expect($john)->not->toBeNull();
    expect($john->hasRole('legal'))->toBeTrue();

    $jane = User::where('email', 'jane@digittal.io')->first();
    expect($jane)->not->toBeNull();
    expect($jane->hasRole('commercial'))->toBeTrue();

    unlink($path);
});

it('fails user import with invalid role', function () {
    $csv = "name,email,roles\nBad Role,bad@test.com,superadmin\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('users', $path);

    expect($result['success'])->toBe(0);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain("Invalid role");

    unlink($path);
});

it('does not duplicate existing users on re-import', function () {
    User::factory()->create(['email' => 'existing@digittal.io', 'name' => 'Original']);

    $csv = "name,email,roles\nUpdated Name,existing@digittal.io,finance\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('users', $path);

    expect($result['success'])->toBe(0);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain('already exists');
    expect(User::where('email', 'existing@digittal.io')->count())->toBe(1);
    // Original user is unchanged
    expect(User::where('email', 'existing@digittal.io')->first()->name)->toBe('Original');

    unlink($path);
});

// ── Counterparty import ──────────────────────────────────────────────────

it('imports counterparties from CSV', function () {
    $csv = "legal_name,registration_number,address,jurisdiction,status\nAcme Corp,REG-123,123 Main St,AE,Active\nBeta Inc,,,GB,Suspended\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('counterparties', $path);

    expect($result['success'])->toBe(2);
    $this->assertDatabaseHas('counterparties', ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123', 'jurisdiction' => 'AE']);
    $this->assertDatabaseHas('counterparties', ['legal_name' => 'Beta Inc', 'status' => 'Suspended']);

    unlink($path);
});

it('imports counterparties with only required columns', function () {
    $csv = "legal_name\nMinimal Corp\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('counterparties', $path);

    expect($result['success'])->toBe(1);
    $this->assertDatabaseHas('counterparties', ['legal_name' => 'Minimal Corp', 'status' => 'Active']);

    unlink($path);
});

// ── Validation tests ─────────────────────────────────────────────────────

it('rejects CSV with missing required columns', function () {
    $csv = "wrong_column\nsome data\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('regions', $path);

    expect($result['success'])->toBe(0);
    expect($result['errors'][0])->toContain('Missing required columns: name');

    unlink($path);
});

it('rejects empty CSV file', function () {
    $path = writeTempCsv('');

    $result = app(BulkDataImportService::class)->import('regions', $path);

    expect($result['success'])->toBe(0);
    expect($result['errors'][0])->toContain('empty or has no headers');

    unlink($path);
});

it('handles column count mismatch gracefully', function () {
    $csv = "name,code\nGood Region,GR\nBad Row\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('regions', $path);

    expect($result['success'])->toBe(1);
    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain('column count mismatch');

    unlink($path);
});

it('handles mixed success and failure rows', function () {
    Region::create(['name' => 'Existing Region', 'code' => 'EX']);

    $csv = "region_code,name\nEX,Good Entity\nBAD,Bad Entity\nEX,Another Good\n";
    $path = writeTempCsv($csv);

    $result = app(BulkDataImportService::class)->import('entities', $path);

    expect($result['success'])->toBe(2);
    expect($result['failed'])->toBe(1);

    unlink($path);
});

// ── Template generation ──────────────────────────────────────────────────

it('generates CSV template for each type', function () {
    $service = app(BulkDataImportService::class);

    expect($service->generateTemplate('regions'))->toBe("name,code\n");
    expect($service->generateTemplate('entities'))->toBe("region_code,name,code,legal_name,registration_number\n");
    expect($service->generateTemplate('projects'))->toBe("entity_code,name,code\n");

    $usersTemplate = $service->generateTemplate('users');
    expect($usersTemplate)->toContain("name,email,roles\n");
    expect($usersTemplate)->toContain("Jane Smith,jane@company.com,legal\n");
    expect($usersTemplate)->toContain("John Doe,john@company.com,\"commercial,finance\"\n");

    expect($service->generateTemplate('counterparties'))->toBe("legal_name,registration_number,address,jurisdiction,status\n");
});
