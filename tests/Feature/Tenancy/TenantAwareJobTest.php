<?php

declare(strict_types=1);

use App\Jobs\TenantAwareJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\WithTenancy;

uses(WithTenancy::class);

afterEach(function (): void {
    $this->tearDownTenancy();
});

// Concrete subclass for testing the abstract TenantAwareJob
function makeTenantAwareJobInstance(): object
{
    return new class extends TenantAwareJob
    {
        public function handle(): void {}

        public function getResolvedTenantId(): ?string
        {
            return $this->tenantId();
        }
    };
}

it('returns null when no tenant is initialized', function (): void {
    $job = makeTenantAwareJobInstance();

    expect($job->getResolvedTenantId())->toBeNull();
});

it('returns the tenant key when tenancy is initialized', function (): void {
    $this->initializeTenancy();

    $job = makeTenantAwareJobInstance();

    expect($job->getResolvedTenantId())->toBe($this->tenant->getTenantKey());
});

it('withTenant sets the explicit tenant ID on the job', function (): void {
    Event::fake([TenantCreated::class]);

    $tenant = Tenant::create([
        'id' => fake()->uuid(),
        'name' => 'Another Tenant',
        'slug' => 'another',
        'status' => Tenant::STATUS_ACTIVE,
    ]);

    $job = makeTenantAwareJobInstance();
    $result = $job->withTenant($tenant);

    expect($result)->toBe($job)
        ->and($job->getResolvedTenantId())->toBe($tenant->getTenantKey());
});

it('explicit tenantId takes priority over the tenancy context', function (): void {
    $this->initializeTenancy(); // also suppresses TenantCreated via Event::fake()

    $otherTenant = Tenant::create([
        'id' => fake()->uuid(),
        'name' => 'Other Tenant',
        'slug' => 'other',
        'status' => Tenant::STATUS_ACTIVE,
    ]);

    $job = makeTenantAwareJobInstance();
    $job->withTenant($otherTenant);

    // Context is $this->tenant but explicit override is $otherTenant
    expect($job->getResolvedTenantId())->toBe($otherTenant->getTenantKey())
        ->and($job->getResolvedTenantId())->not->toBe($this->tenant->getTenantKey());
});
