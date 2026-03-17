<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class))) {
            // TODO(remove-after-f6): FakeTenancy escape hatch.
            // CCRS migrations were moved to database/migrations/tenant/ for stancl/tenancy.
            // In tests we run them on the same :memory: SQLite connection so existing tests
            // continue to pass without a real tenant DB context. New tests that need proper
            // tenant isolation should use the WithTenancy trait instead of relying on this.
            $this->artisan('migrate', [
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
            ]);

            $this->seed(\Database\Seeders\RoleSeeder::class);
        }
    }
}
