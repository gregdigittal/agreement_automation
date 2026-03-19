<?php

declare(strict_types=1);

use App\Http\Controllers\VendorAuthController;
use App\Models\Contract;
use App\Services\ContractFileService;
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
| Two middleware groups:
|   - Tenant-required (+ PreventAccessFromCentralDomains): routes that must
|     always run inside a tenant and must block central-domain access.
|   - Tenant-preferred (InitializeTenancyByDomain only): routes that need
|     tenant context on tenant subdomains but remain accessible on central
|     domains / localhost in development (vendor portal, signing, etc.).
|
*/

// ── Tenant-required routes ───────────────────────────────────────────────────
// Blocks access from central domains. Filament admin panel routes are
// auto-registered by AdminPanelProvider with the same middleware set.
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Filament admin panel routes are auto-registered by AdminPanelProvider.
    // Add custom tenant-scoped routes here that must NEVER run in central context.
});

// ── Tenant-preferred routes (Vendor Portal) ──────────────────────────────────
// InitializeTenancyByDomain initialises tenant context from the request domain.
// On central domains / localhost the $onFail hook passes through (see
// TenancyServiceProvider::configureMiddlewareFallbacks), so these routes remain
// reachable in development without a live tenant domain.
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
])->group(function () {
    // Vendor Portal — VendorUser and VendorLoginToken live in the tenant database.
    // Tenant context is required for correct DB routing and magic-link URL generation.
    Route::get('/vendor/login', fn () => view('vendor.login'))->name('vendor.login');
    Route::post('/vendor/auth/request', [VendorAuthController::class, 'requestLink'])
        ->name('vendor.auth.request')
        ->middleware('throttle:magic-link');
    Route::get('/vendor/auth/verify/{token}', [VendorAuthController::class, 'verify'])
        ->name('vendor.auth.verify');
    Route::post('/vendor/logout', [VendorAuthController::class, 'logout'])
        ->name('vendor.logout');

    Route::get('/vendor/contracts/{contract}/download', function (Contract $contract) {
        $user = auth('vendor')->user();
        if (! $user || $contract->counterparty_id !== $user->counterparty_id) {
            abort(403);
        }
        if (! $contract->storage_path) {
            abort(404, 'No document uploaded for this contract.');
        }
        $url = app(ContractFileService::class)->getSignedUrl($contract->storage_path);

        return $url ? redirect($url) : abort(404);
    })->middleware('auth:vendor')->name('vendor.contract.download');
});
