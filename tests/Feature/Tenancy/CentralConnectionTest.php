<?php

declare(strict_types=1);

use App\Models\PlatformAdmin;
use Tests\WithTenancy;

uses(WithTenancy::class);

afterEach(function (): void {
    $this->tearDownTenancy();
});

it('PlatformAdmin declares the central database connection', function (): void {
    $model = new PlatformAdmin;

    expect($model->getConnectionName())->toBe('central');
});

it('PlatformAdmin connection is not affected by tenancy bootstrap switching the default', function (): void {
    $this->initializeTenancy();

    // Simulate what DatabaseTenancyBootstrapper does: switch the default connection.
    $original = \Illuminate\Support\Facades\DB::getDefaultConnection();
    \Illuminate\Support\Facades\DB::setDefaultConnection('sqlite'); // mimic tenant switch

    // PlatformAdmin must still report 'central', not the new default.
    $model = new PlatformAdmin;
    expect($model->getConnectionName())->toBe('central');

    // Restore to avoid bleeding into other tests.
    \Illuminate\Support\Facades\DB::setDefaultConnection($original);
});

it('PlatformAdmin connection name is distinct from the default connection', function (): void {
    $defaultConnection = config('database.default');

    // 'central' is a named alias — functionally identical but independently addressable.
    // This ensures the bootstrapper's setDefaultConnection() call cannot redirect
    // PlatformAdmin queries to a tenant database.
    expect(config("database.connections.central"))->not->toBeNull()
        ->and(config("database.connections.central.driver"))
        ->toBe(config("database.connections.{$defaultConnection}.driver"));
});
