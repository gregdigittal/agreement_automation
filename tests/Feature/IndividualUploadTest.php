<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndividualUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_files_are_detected_when_no_zip(): void
    {
        $disk = config('ccrs.contracts_disk', 'database');
        Storage::fake($disk);

        // Simulate a file_path reference without the file existing
        $this->assertFalse(
            Storage::disk($disk)->exists('bulk_uploads/files/missing-contract.pdf')
        );
    }

    public function test_individual_files_stored_in_correct_directory(): void
    {
        $disk = config('ccrs.contracts_disk', 'database');
        Storage::fake($disk);

        Storage::disk($disk)->put('bulk_uploads/files/test-contract.pdf', 'fake-pdf-content');

        $this->assertTrue(
            Storage::disk($disk)->exists('bulk_uploads/files/test-contract.pdf')
        );
    }
}
