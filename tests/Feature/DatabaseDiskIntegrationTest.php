<?php

use App\Models\FileStorage;
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

    $response = $this->get($parsedUrl['path'] . '?' . $queryString);
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
