<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Routes registered here run inside the tenant context (domain identified
| via InitializeTenancyByDomain). Filament's admin panel handles its own
| routing via AdminPanelProvider — do not register /admin routes here.
|
| Add any non-Filament tenant-scoped web routes below.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Filament admin panel routes are auto-registered by AdminPanelProvider.
    // Add custom tenant-scoped web routes here as needed.
});
