<?php

use App\Models\User;
use App\Services\BulkDataImportService;
use Illuminate\Support\Facades\Mail;

// 1. Bulk import creates users with single role
it('imports users with single role from CSV', function () {
    Mail::fake();

    $csv = "name,email,roles\nAlice,alice@example.com,legal\n";
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);

    $service = app(BulkDataImportService::class);
    $result = $service->import('users', $path);

    expect($result['success'])->toBe(1);
    expect($result['failed'])->toBe(0);

    $user = User::where('email', 'alice@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->status->value)->toBe('active');
    expect($user->hasRole('legal'))->toBeTrue();

    Mail::assertQueued(\App\Mail\UserInviteMail::class, function ($mail) {
        return $mail->hasTo('alice@example.com');
    });

    unlink($path);
});

// 2. Bulk import creates users with multiple roles
it('imports users with multiple comma-separated roles', function () {
    Mail::fake();

    $csv = "name,email,roles\nBob,bob@example.com,\"commercial,finance\"\n";
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);

    $service = app(BulkDataImportService::class);
    $result = $service->import('users', $path);

    expect($result['success'])->toBe(1);
    $user = User::where('email', 'bob@example.com')->first();
    expect($user->hasRole('commercial'))->toBeTrue();
    expect($user->hasRole('finance'))->toBeTrue();

    unlink($path);
});

// 3. Rejects invalid role names
it('rejects rows with invalid role names', function () {
    Mail::fake();

    $csv = "name,email,roles\nBad,bad@example.com,superadmin\n";
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);

    $service = app(BulkDataImportService::class);
    $result = $service->import('users', $path);

    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain('Invalid role');

    unlink($path);
});

// 4. Skips existing users
it('skips existing users and reports warning', function () {
    Mail::fake();
    User::factory()->create(['email' => 'existing@example.com']);

    $csv = "name,email,roles\nExisting,existing@example.com,legal\n";
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);

    $service = app(BulkDataImportService::class);
    $result = $service->import('users', $path);

    expect($result['failed'])->toBe(1);
    expect($result['errors'][0])->toContain('already exists');

    unlink($path);
});

// 5. Template download has correct headers
it('generates CSV template with correct headers for users', function () {
    $service = app(BulkDataImportService::class);
    $csv = $service->generateTemplate('users');

    expect($csv)->toContain('name,email,roles');
    expect($csv)->toContain('legal');
});
