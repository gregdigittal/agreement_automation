<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('signing routes carry InitializeTenancyByDomain for tenant-aware token lookup', function (): void {
    $signingRoutes = ['signing.show', 'signing.document', 'signing.submit', 'signing.decline'];

    foreach ($signingRoutes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("Route '{$routeName}' should be registered")
            ->and(collect($route->gatherMiddleware())->contains(
                fn (string $m): bool => str_contains($m, 'InitializeTenancyByDomain')
            ))->toBeTrue("Route '{$routeName}' should have InitializeTenancyByDomain");
    }
});

it('signing routes do NOT have PreventAccessFromCentralDomains', function (): void {
    // Signing routes use the tenant-preferred group — accessible on central/localhost
    // for development, but tenant-aware on tenant subdomains.
    foreach (['signing.show', 'signing.document', 'signing.submit', 'signing.decline'] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect(collect($route->gatherMiddleware())->contains(
            fn (string $m): bool => str_contains($m, 'PreventAccessFromCentralDomains')
        ))->toBeFalse("Route '{$routeName}' should NOT block central domains");
    }
});
