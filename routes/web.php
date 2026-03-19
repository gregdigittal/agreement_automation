<?php

use App\Http\Controllers\Auth\AzureAdController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Kubernetes liveness probe — returns 200 if app is running
Route::get('/health', function () {
    return response('ok', 200);
})->name('health');

// Kubernetes readiness probe — returns 200 only if DB is reachable
Route::get('/health/ready', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();

        return response()->json(['status' => 'ready']);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Health readiness check failed', ['error' => $e->getMessage()]);

        return response()->json(['status' => 'not_ready'], 503);
    }
})->name('health.ready');

// Database file storage — signed URL serving (replaces S3 pre-signed URLs)
// Signed URL is the first gate. Per-path authorization (contract access, vendor guard)
// is enforced inside StorageServeController::authorise() — not here — so that non-contract
// paths (e.g. bulk export files) work with a signed URL alone.
Route::get('/storage/serve/{path}', \App\Http\Controllers\StorageServeController::class)
    ->where('path', '.*')
    ->middleware(['signed'])
    ->name('storage.serve');

Route::get('/', function () {
    return redirect(auth()->check() ? '/admin' : '/admin/login');
});

Route::get('/login', fn () => redirect('/admin/login'))->name('login');
// Azure SSO — InitializeTenancyByDomain middleware ensures the User is created in
// the correct tenant database on callback. $onFail passes through on central/localhost
// so development and sandbox environments work without a tenant domain.
Route::middleware(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class)->group(function () {
    Route::get('/auth/azure/redirect', [AzureAdController::class, 'redirect'])->name('azure.redirect');
    Route::get('/auth/azure/callback', [AzureAdController::class, 'callback'])->name('azure.callback');
});
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');

Route::get('/contracts/{contract}/download', function (\App\Models\Contract $contract) {
    if (! auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial', 'finance', 'audit'])) {
        abort(403);
    }
    if (! $contract->storage_path) {
        abort(404, 'No document uploaded for this contract.');
    }
    $service = app(\App\Services\ContractFileService::class);
    $url = $service->getSignedUrl($contract->storage_path);

    return $url ? redirect($url) : abort(404);
})->middleware('auth')->name('contract.download');

// Vendor Portal Auth routes are registered in routes/tenant.php so that
// InitializeTenancyByDomain runs and VendorUser queries are routed to the
// correct tenant database. See routes/tenant.php for all /vendor/* routes.

use App\Http\Controllers\Reports\ReportExportController;

Route::prefix('reports/export')->middleware('auth')->group(function () {
    Route::get('/contracts/excel', [ReportExportController::class, 'contractsExcel'])->name('reports.export.contracts.excel');
    Route::get('/contracts/pdf', [ReportExportController::class, 'contractsPdf'])->name('reports.export.contracts.pdf');

    Route::get('/analytics/pdf', [ReportExportController::class, 'analyticsPdf'])->name('reports.export.analytics.pdf');
    Route::get('/compliance/{contract_id}/pdf', [ReportExportController::class, 'compliancePdf'])->name('reports.export.compliance.pdf');
    Route::get('/obligations/excel', [ReportExportController::class, 'obligationsExcel'])->name('reports.export.obligations.excel');
});

// E-Signing (public, token-based auth) — C2: throttled to prevent brute-force
// InitializeTenancyByDomain ensures SigningSessionSigner token lookups are routed to the
// correct tenant database when a signer visits on a tenant subdomain (e.g. acme-ccrs.digittal.mobi).
// $onFail passes through on central domains / localhost — see TenancyServiceProvider::configureMiddlewareFallbacks().
Route::middleware([\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class])
    ->group(function () {
        Route::prefix('sign')->middleware('throttle:signing')->group(function () {
            Route::get('/{token}', [\App\Http\Controllers\SigningController::class, 'show'])
                ->name('signing.show');
            Route::get('/{token}/document', [\App\Http\Controllers\SigningController::class, 'document'])
                ->name('signing.document');
            Route::post('/{token}/submit', [\App\Http\Controllers\SigningController::class, 'submit'])
                ->name('signing.submit');
            Route::post('/{token}/decline', [\App\Http\Controllers\SigningController::class, 'decline'])
                ->name('signing.decline');
        });
    });
