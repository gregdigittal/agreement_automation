<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('vendor portal routes are registered with InitializeTenancyByDomain middleware', function (): void {
    $vendorRoutes = [
        'vendor.login',
        'vendor.auth.request',
        'vendor.auth.verify',
        'vendor.logout',
        'vendor.contract.download',
    ];

    foreach ($vendorRoutes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("Route '{$routeName}' should be registered")
            ->and(collect($route->gatherMiddleware())->contains(
                fn (string $m): bool => str_contains($m, 'InitializeTenancyByDomain')
            ))->toBeTrue("Route '{$routeName}' should have InitializeTenancyByDomain middleware");
    }
});

it('vendor portal routes do NOT have PreventAccessFromCentralDomains middleware', function (): void {
    // Vendor routes use the tenant-preferred group — accessible on central/localhost
    // for development, but tenant-aware on tenant subdomains.
    $vendorRoutes = ['vendor.login', 'vendor.auth.request', 'vendor.auth.verify'];

    foreach ($vendorRoutes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect(collect($route->gatherMiddleware())->contains(
            fn (string $m): bool => str_contains($m, 'PreventAccessFromCentralDomains')
        ))->toBeFalse("Route '{$routeName}' should NOT block central domains");
    }
});

it('Azure SSO routes have InitializeTenancyByDomain for tenant-aware user creation', function (): void {
    foreach (['azure.redirect', 'azure.callback'] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and(collect($route->gatherMiddleware())->contains(
                fn (string $m): bool => str_contains($m, 'InitializeTenancyByDomain')
            ))->toBeTrue("Route '{$routeName}' should have InitializeTenancyByDomain");
    }
});
