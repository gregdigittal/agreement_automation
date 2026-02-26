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

    $job = new GenerateWeeklyReport();
    $job->handle();

    Storage::disk(config('ccrs.contracts_disk'))->assertDirectoryEmpty('reports');
});

it('generates PDF and stores to S3 when enabled', function () {
    config()->set('features.advanced_analytics', true);
    Mail::fake();

    $user = User::factory()->create();
    $user->assignRole('system_admin');

    $job = new GenerateWeeklyReport();
    $job->handle();

    $files = Storage::disk(config('ccrs.contracts_disk'))->allFiles('reports/weekly');
    expect($files)->not->toBeEmpty();
});

it('can be dispatched to the queue', function () {
    Queue::fake();
    GenerateWeeklyReport::dispatch();
    Queue::assertPushed(GenerateWeeklyReport::class);
});

it('compiles report data without errors', function () {
    config()->set('features.advanced_analytics', true);
    Mail::fake();

    // Create some contracts to ensure report data is populated
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    \App\Models\Contract::factory()->create(['workflow_state' => 'draft']);
    \App\Models\Contract::factory()->create(['workflow_state' => 'executed']);

    $job = new GenerateWeeklyReport();
    $job->handle();

    // PDF was generated and stored
    $files = Storage::disk(config('ccrs.contracts_disk'))->allFiles('reports/weekly');
    expect($files)->toHaveCount(1);
    expect($files[0])->toContain('ccrs-weekly-report-');
});
