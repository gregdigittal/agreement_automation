<?php

declare(strict_types=1);

/**
 * F-6 Multi-Tenancy Smoke Test
 *
 * Validates the F-6 architectural guarantees at the code/integration level:
 *
 *   1. Central schema:   tenants, domains, platform_admins tables exist (central DB).
 *   2. Tenant schema:    all CCRS tables migrate on the tenant path.
 *   3. Context:          WithTenancy initialises context without DB switch.
 *   4. TenantCache:      keys scoped per tenant, isolated across tenants.
 *   5. TenantAwareJob:   propagates tenant ID from context and via withTenant().
 *   6. Route middleware: tenant and vendor routes carry InitializeTenancyByDomain.
 *   7. PlatformAdmin:    pinned to central connection, unaffected by tenant context.
 *   8. tenant:create:    artisan command provisions tenant + domain.
 *
 * These tests run against the FakeTenancy escape hatch (shared SQLite :memory:
 * DB) — not real per-tenant database isolation. Full DB isolation is validated
 * in the sandbox smoke test (requires live MySQL + real tenant provisioning).
 */

use App\Helpers\TenantCache;
use App\Jobs\TenantAwareJob;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\WithTenancy;

uses(WithTenancy::class);

afterEach(function (): void {
    $this->tearDownTenancy();
});

// ── 1. Central schema ────────────────────────────────────────────────────────

it('central migrations created tenants and platform_admins tables', function (): void {
    expect(Schema::hasTable('tenants'))->toBeTrue()
        ->and(Schema::hasTable('domains'))->toBeTrue()
        ->and(Schema::hasTable('platform_admins'))->toBeTrue();
});

// ── 2. Tenant schema ─────────────────────────────────────────────────────────

it('tenant migrations created all core CCRS tables', function (): void {
    // Spot-check key tables across all CCRS modules.
    $expectedTables = [
        'users', 'contracts', 'counterparties', 'entities',
        'regions', 'projects', 'workflow_templates', 'audit_log',
        'signing_sessions', 'vendor_users', 'notifications',
        'ai_analysis_results',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected tenant table '{$table}' to exist");
    }
});

// ── 3. Tenant context ────────────────────────────────────────────────────────

it('WithTenancy trait initialises tenancy context and exposes $this->tenant', function (): void {
    $this->initializeTenancy();

    expect(tenancy()->initialized)->toBeTrue()
        ->and($this->tenant)->toBeInstanceOf(Tenant::class)
        ->and($this->tenant->status)->toBe(Tenant::STATUS_ACTIVE);
});

it('tenancy context ends cleanly without crashing', function (): void {
    $this->initializeTenancy();

    // tearDownTenancy() is called in afterEach — calling it early to verify
    $this->tearDownTenancy();

    expect(tenancy()->initialized)->toBeFalse();
});

// ── 4. TenantCache scoping ───────────────────────────────────────────────────

it('TenantCache produces tenant-scoped keys in tenant context', function (): void {
    $this->initializeTenancy();

    $key = TenantCache::key('contract_types.options');

    expect($key)->toStartWith($this->tenant->getTenantKey().':')
        ->and($key)->toEndWith(':contract_types.options');
});

it('TenantCache keys are unique across two different tenants', function (): void {
    Event::fake([TenantCreated::class]);

    $tenantA = Tenant::create(['id' => fake()->uuid(), 'name' => 'Acme Corp', 'slug' => 'acme', 'status' => Tenant::STATUS_ACTIVE]);
    $tenantB = Tenant::create(['id' => fake()->uuid(), 'name' => 'Beta Ltd', 'slug' => 'beta', 'status' => Tenant::STATUS_ACTIVE]);

    $bootstrappers = config('tenancy.bootstrappers');
    config(['tenancy.bootstrappers' => array_filter($bootstrappers, fn ($b) => $b !== \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class)]);

    tenancy()->initialize($tenantA);
    $keyA = TenantCache::key('org_structure_tree_data');
    tenancy()->end();

    tenancy()->initialize($tenantB);
    $keyB = TenantCache::key('org_structure_tree_data');
    tenancy()->end();

    config(['tenancy.bootstrappers' => $bootstrappers]);

    expect($keyA)->not->toBe($keyB)
        ->and($keyA)->toContain($tenantA->getTenantKey())
        ->and($keyB)->toContain($tenantB->getTenantKey());
});

// ── 5. TenantAwareJob ────────────────────────────────────────────────────────

it('TenantAwareJob resolves tenant ID from active context', function (): void {
    $this->initializeTenancy();

    $job = new class extends TenantAwareJob
    {
        public function handle(): void {}

        public function resolvedId(): ?string { return $this->tenantId(); }
    };

    expect($job->resolvedId())->toBe($this->tenant->getTenantKey());
});

it('TenantAwareJob withTenant() overrides the active context tenant', function (): void {
    $this->initializeTenancy();

    Event::fake([TenantCreated::class]);
    $other = Tenant::create(['id' => fake()->uuid(), 'name' => 'Other', 'slug' => 'other', 'status' => Tenant::STATUS_ACTIVE]);

    $job = new class extends TenantAwareJob
    {
        public function handle(): void {}

        public function resolvedId(): ?string { return $this->tenantId(); }
    };

    $job->withTenant($other);

    expect($job->resolvedId())
        ->toBe($other->getTenantKey())
        ->not->toBe($this->tenant->getTenantKey());
});

// ── 6. Route middleware ───────────────────────────────────────────────────────

it('admin panel middleware includes InitializeTenancyByDomain before EncryptCookies', function (): void {
    $panel = \Filament\Facades\Filament::getPanel('admin');
    $middleware = $panel->getMiddleware();

    $tenancyIdx = array_search(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class, $middleware);
    $cookiesIdx = array_search(\Illuminate\Cookie\Middleware\EncryptCookies::class, $middleware);

    expect($tenancyIdx)->not->toBeFalse('InitializeTenancyByDomain should be in admin panel middleware')
        ->and($cookiesIdx)->not->toBeFalse()
        ->and($tenancyIdx)->toBeLessThan($cookiesIdx);
});

it('platform panel does not include InitializeTenancyByDomain', function (): void {
    $panel = \Filament\Facades\Filament::getPanel('platform');
    $middleware = $panel->getMiddleware();

    expect($middleware)->not->toContain(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);
});

// ── 7. PlatformAdmin central connection ──────────────────────────────────────

it('PlatformAdmin is pinned to the central connection and ignores default connection switches', function (): void {
    $originalDefault = \Illuminate\Support\Facades\DB::getDefaultConnection();

    // Simulate the bootstrapper switching the default connection.
    \Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');
    $connectionName = (new PlatformAdmin)->getConnectionName();
    \Illuminate\Support\Facades\DB::setDefaultConnection($originalDefault);

    expect($connectionName)->toBe('central');
});

// ── 8. tenant:create command ─────────────────────────────────────────────────

it('tenant:create command provisions a Tenant record and its domain', function (): void {
    Event::fake([TenantCreated::class]);

    Artisan::call('tenant:create', [
        '--name' => 'Smoke Test Corp',
        '--slug' => 'smoke-test',
    ]);

    $tenant = Tenant::where('slug', 'smoke-test')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Smoke Test Corp')
        ->and($tenant->status)->toBe(Tenant::STATUS_ACTIVE);

    $domain = $tenant->domains()->where('domain', 'smoke-test-ccrs.digittal.mobi')->first();
    expect($domain)->not->toBeNull();
});
