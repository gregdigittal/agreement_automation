<?php

use App\Models\Contract;
use App\Models\FileStorage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('registers the database disk driver', function () {
    $disk = Storage::disk('database');
    expect($disk)->toBeInstanceOf(\Illuminate\Filesystem\FilesystemAdapter::class);
});

it('stores and retrieves a file via Storage facade', function () {
    Storage::disk('database')->put('integration/test.txt', 'Hello from DB');

    expect(Storage::disk('database')->exists('integration/test.txt'))->toBeTrue();
    expect(Storage::disk('database')->get('integration/test.txt'))->toBe('Hello from DB');

    $record = FileStorage::where('path', 'integration/test.txt')->first();
    expect($record)->not->toBeNull();
    expect($record->size)->toBe(13);
});

it('generates a temporary URL via Storage facade', function () {
    Storage::disk('database')->put('integration/url-test.pdf', '%PDF-test');

    $url = Storage::disk('database')->temporaryUrl('integration/url-test.pdf', now()->addMinutes(5));

    expect($url)->toContain('/storage/serve/');
    expect($url)->toContain('signature=');
});

it('serves files via signed URL controller', function () {
    Storage::disk('database')->put('integration/serve-test.pdf', '%PDF-1.4 test content');

    $url = Storage::disk('database')->temporaryUrl('integration/serve-test.pdf', now()->addMinutes(5));

    // Extract the path from the signed URL for a relative request
    $parsedUrl = parse_url($url);
    $queryString = $parsedUrl['query'] ?? '';

    $response = $this->get($parsedUrl['path'].'?'.$queryString);
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    // StreamedResponse: use streamedContent() or capture output buffer
    expect($response->streamedContent())->toBe('%PDF-1.4 test content');
});

it('rejects unsigned requests to serve endpoint', function () {
    Storage::disk('database')->put('integration/no-sign.pdf', 'secret');

    $this->get('/storage/serve/integration/no-sign.pdf')
        ->assertStatus(403);
});

it('deletes files via Storage facade', function () {
    Storage::disk('database')->put('integration/delete.txt', 'bye');
    expect(Storage::disk('database')->exists('integration/delete.txt'))->toBeTrue();

    Storage::disk('database')->delete('integration/delete.txt');
    expect(Storage::disk('database')->exists('integration/delete.txt'))->toBeFalse();
    expect(FileStorage::where('path', 'integration/delete.txt')->exists())->toBeFalse();
});

it('ccrs.contracts_disk config defaults to database', function () {
    expect(config('ccrs.contracts_disk'))->toBe('database');
});

// ---------------------------------------------------------------------------
// S3 / SeaweedFS disk branch — StorageServeController
// ---------------------------------------------------------------------------

it('StorageServeController redirects to pre-signed URL when disk is s3', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('integration/s3-test.pdf', '%PDF-1.4 s3 content');

    config(['ccrs.contracts_disk' => 's3']);

    $user = User::factory()->create();
    $user->assignRole('system_admin');

    // Use a non-contracts/ path so no contract FK lookup is needed
    $url = Storage::disk('database')->temporaryUrl('integration/s3-test.pdf', now()->addMinutes(5));
    $parsedUrl = parse_url($url);

    // Replace the database disk URL with a direct serve path to test the S3 branch
    $response = $this->actingAs($user)->get(route('storage.serve', ['path' => 'integration/s3-test.pdf']).'?'.($parsedUrl['query'] ?? ''));

    // S3 branch redirects — the 302 proves the S3 path was taken, not the BLOB stream path
    $response->assertRedirect();
});

it('StorageServeController returns 404 when file not found on s3 disk', function () {
    Storage::fake('s3');
    config(['ccrs.contracts_disk' => 's3']);

    $user = User::factory()->create();
    $user->assignRole('system_admin');

    $url = Storage::disk('database')->temporaryUrl('integration/missing.pdf', now()->addMinutes(5));
    $parsedUrl = parse_url($url);

    $response = $this->actingAs($user)->get(route('storage.serve', ['path' => 'integration/missing.pdf']).'?'.($parsedUrl['query'] ?? ''));

    $response->assertNotFound();
});

it('StorageServeController streams BLOB when disk is database', function () {
    Storage::disk('database')->put('integration/blob-branch.pdf', '%PDF-1.4 blob');
    config(['ccrs.contracts_disk' => 'database']);

    $user = User::factory()->create();
    $user->assignRole('system_admin');

    $url = Storage::disk('database')->temporaryUrl('integration/blob-branch.pdf', now()->addMinutes(5));
    $parsedUrl = parse_url($url);

    $response = $this->actingAs($user)->get($parsedUrl['path'].'?'.($parsedUrl['query'] ?? ''));

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-1.4 blob');
});
