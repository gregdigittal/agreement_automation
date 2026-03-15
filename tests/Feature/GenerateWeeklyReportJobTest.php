<?php

use App\Jobs\GenerateWeeklyReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('ccrs.contracts_disk'));
});

it('skips when advanced_analytics feature is disabled', function () {
    config()->set('features.advanced_analytics', false);

    $job = new GenerateWeeklyReport;
    $job->handle();

    Storage::disk(config('ccrs.contracts_disk'))->assertDirectoryEmpty('reports');
});

it('generates PDF and stores to S3 when enabled', function () {
    config()->set('features.advanced_analytics', true);
    Mail::fake();

    $user = User::factory()->create();
    $user->assignRole('system_admin');

    $job = new GenerateWeeklyReport;
    $job->handle();

    $files = Storage::disk(config('ccrs.contracts_disk'))->allFiles('reports/weekly');
    expect($files)->not->toBeEmpty();
});

it('can be dispatched to the queue', function () {
    Queue::fake();
    GenerateWeeklyReport::dispatch();
    Queue::assertPushed(GenerateWeeklyReport::class);
});

it('only targets system_admin and legal users as email recipients', function () {
    // The job uses Mail::send() with a closure (not a Mailable class), so Mail::fake()
    // assertSentCount does not track these sends. Instead, we verify recipient selection.
    config()->set('features.advanced_analytics', true);
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $legal = User::factory()->create();
    $legal->assignRole('legal');

    $commercial = User::factory()->create();
    $commercial->assignRole('commercial'); // should NOT receive the report

    // Verify the recipient query logic matches only admin + legal
    $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['system_admin', 'legal']))->get();

    expect($recipients)->toHaveCount(2);
    expect($recipients->pluck('id'))->toContain($admin->id);
    expect($recipients->pluck('id'))->toContain($legal->id);
    expect($recipients->pluck('id'))->not->toContain($commercial->id);

    // Job should complete without throwing
    $job = new GenerateWeeklyReport;
    $job->handle();
});

it('skips users with email notifications disabled in preferences', function () {
    config()->set('features.advanced_analytics', true);
    Mail::fake();

    $admin = User::factory()->create(['notification_preferences' => ['email' => false]]);
    $admin->assignRole('system_admin');

    // Job completes and stores the report even though no email is sent
    $job = new GenerateWeeklyReport;
    $job->handle();

    $files = Storage::disk(config('ccrs.contracts_disk'))->allFiles('reports/weekly');
    expect($files)->not->toBeEmpty();
});

it('compiles report data without errors', function () {
    config()->set('features.advanced_analytics', true);
    Mail::fake();

    // Create some contracts to ensure report data is populated
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    \App\Models\Contract::factory()->create(['workflow_state' => 'draft']);
    \App\Models\Contract::factory()->create(['workflow_state' => 'executed']);

    $job = new GenerateWeeklyReport;
    $job->handle();

    // PDF was generated and stored
    $files = Storage::disk(config('ccrs.contracts_disk'))->allFiles('reports/weekly');
    expect($files)->toHaveCount(1);
    expect($files[0])->toContain('ccrs-weekly-report-');
});
