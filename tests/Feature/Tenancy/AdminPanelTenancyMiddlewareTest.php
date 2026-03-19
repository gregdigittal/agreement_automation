<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Verify the admin Filament panel has InitializeTenancyByDomain registered
 * in its middleware stack (T-3). Requests from central domains (localhost,
 * 127.0.0.1) are a no-op for this middleware, so existing HTTP tests are
 * unaffected. Tenant-domain requests will bootstrap the tenant DB context.
 */
it('admin panel has InitializeTenancyByDomain in its middleware stack', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $panel = Filament::getPanel('admin');
    $middleware = $panel->getMiddleware();

    expect($middleware)->toContain(InitializeTenancyByDomain::class);
});

it('InitializeTenancyByDomain runs before EncryptCookies on the admin panel', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $panel = Filament::getPanel('admin');
    $middleware = $panel->getMiddleware();

    $tenancyPos = array_search(InitializeTenancyByDomain::class, $middleware, strict: true);
    $cookiesPos = array_search(\Illuminate\Cookie\Middleware\EncryptCookies::class, $middleware, strict: true);

    expect($tenancyPos)->not->toBeFalse()
        ->and($cookiesPos)->not->toBeFalse()
        ->and($tenancyPos)->toBeLessThan($cookiesPos);
});
