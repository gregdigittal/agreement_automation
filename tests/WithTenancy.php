<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

/**
 * Provides a tenant context for tests that need tenancy()->tenant to be set.
 *
 * This trait initialises the tenancy context (bootstrappers: Cache, Queue)
 * WITHOUT switching the database connection — the FakeTenancy escape hatch in
 * TestCase already runs all tenant migrations on the shared :memory: SQLite DB.
 *
 * Usage:
 *   use Tests\WithTenancy;
 *   // In setUp():
 *   $this->initializeTenancy();
 *   // In the test body:
 *   $this->tenant  // the active Tenant model
 *
 * For real database-per-tenant isolation, use a dedicated integration test
 * environment (MySQL + stancl/tenancy's SQLite file driver). This trait is
 * for unit and feature tests that need the tenancy *context* only.
 */
trait WithTenancy
{
    protected Tenant $tenant;

    protected function initializeTenancy(): void
    {
        // Suppress TenantCreated event so CreateDatabase/MigrateDatabase jobs
        // don't run — we don't need a real tenant DB in context-only tests.
        Event::fake([TenantCreated::class]);

        $this->tenant = Tenant::create([
            'id' => fake()->uuid(),
            'name' => 'Test Tenant',
            'slug' => 'test',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        // Initialise tenancy without the DatabaseTenancyBootstrapper.
        // Temporarily swap the bootstrappers list so only Cache and Queue
        // bootstrappers run — the DB is already set up via FakeTenancy.
        $originalBootstrappers = config('tenancy.bootstrappers');
        config(['tenancy.bootstrappers' => array_filter(
            $originalBootstrappers,
            fn (string $b): bool => $b !== \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        )]);

        tenancy()->initialize($this->tenant);

        config(['tenancy.bootstrappers' => $originalBootstrappers]);
    }

    protected function tearDownTenancy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
