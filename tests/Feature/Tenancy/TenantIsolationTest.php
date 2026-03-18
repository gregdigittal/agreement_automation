<?php

declare(strict_types=1);

use App\Helpers\TenantCache;
use App\Jobs\TenantAwareJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\WithTenancy;

uses(WithTenancy::class);

afterEach(function (): void {
    $this->tearDownTenancy();
});

// ── TenantCache::key() ──────────────────────────────────────────────────────

it('TenantCache returns the plain key in central context', function (): void {
    // No tenancy initialized — central context (platform panel, artisan, etc.)
    expect(tenancy()->initialized)->toBeFalse();
    expect(TenantCache::key('contract_types.options'))
        ->toBe('contract_types.options');
});

it('TenantCache prefixes the key with the tenant ID when tenancy is active', function (): void {
    $this->initializeTenancy();

    $tenantId = $this->tenant->getTenantKey();

    expect(TenantCache::key('contract_types.options'))
        ->toBe("{$tenantId}:contract_types.options");
});

it('TenantCache produces distinct keys for two different tenants', function (): void {
    Event::fake([TenantCreated::class]);

    $tenantA = Tenant::create(['id' => fake()->uuid(), 'name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => Tenant::STATUS_ACTIVE]);
    $tenantB = Tenant::create(['id' => fake()->uuid(), 'name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => Tenant::STATUS_ACTIVE]);

    $originalBootstrappers = config('tenancy.bootstrappers');
    config(['tenancy.bootstrappers' => array_filter(
        $originalBootstrappers,
        fn (string $b): bool => $b !== \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
    )]);

    tenancy()->initialize($tenantA);
    $keyA = TenantCache::key('org_structure_tree_data');

    tenancy()->end();

    tenancy()->initialize($tenantB);
    $keyB = TenantCache::key('org_structure_tree_data');

    tenancy()->end();

    config(['tenancy.bootstrappers' => $originalBootstrappers]);

    expect($keyA)->not->toBe($keyB)
        ->and($keyA)->toStartWith($tenantA->getTenantKey().':')
        ->and($keyB)->toStartWith($tenantB->getTenantKey().':');
});

it('TenantCache returns the plain key after tenancy is ended', function (): void {
    $this->initializeTenancy();
    $this->tearDownTenancy();

    expect(tenancy()->initialized)->toBeFalse();
    expect(TenantCache::key('sharepoint_graph_token'))
        ->toBe('sharepoint_graph_token');
});

// ── TenantAwareJob in tenant context ────────────────────────────────────────

it('TenantAwareJob tenantId() returns the active tenant key via WithTenancy', function (): void {
    $this->initializeTenancy();

    $job = new class extends TenantAwareJob
    {
        public function handle(): void {}

        public function getResolvedTenantId(): ?string
        {
            return $this->tenantId();
        }
    };

    expect($job->getResolvedTenantId())->toBe($this->tenant->getTenantKey());
});

it('TenantAwareJob explicit withTenant() takes priority over active tenancy context', function (): void {
    $this->initializeTenancy();

    Event::fake([TenantCreated::class]);
    $otherTenant = Tenant::create([
        'id' => fake()->uuid(),
        'name' => 'Other Tenant',
        'slug' => 'other',
        'status' => Tenant::STATUS_ACTIVE,
    ]);

    $job = new class extends TenantAwareJob
    {
        public function handle(): void {}

        public function getResolvedTenantId(): ?string
        {
            return $this->tenantId();
        }
    };

    $job->withTenant($otherTenant);

    // Explicit tenant overrides the initialized tenant context
    expect($job->getResolvedTenantId())->toBe($otherTenant->getTenantKey())
        ->and($job->getResolvedTenantId())->not->toBe($this->tenant->getTenantKey());
});
