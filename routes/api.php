<?php

use App\Helpers\Feature;
use App\Http\Controllers\Api\TitoController;
use App\Http\Controllers\Webhooks\BoldsignWebhookController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

// BoldSign webhook — only registered when in-house signing is disabled (deprecated path).
// In multi-tenant deployments, BoldSign is configured per tenant with the webhook URL
// https://{slug}-ccrs.digittal.mobi/webhooks/boldsign/{tenant_slug}.
// InitializeTenancyByDomain identifies the tenant from the Host header; the {tenant_slug}
// route segment provides a human-readable identifier and is logged for debugging.
if (!Feature::inHouseSigning()) {
    Route::post('/webhooks/boldsign/{tenant_slug}', [BoldsignWebhookController::class, 'handle'])
        ->middleware(InitializeTenancyByDomain::class)
        ->name('webhooks.boldsign');
}

Route::middleware(['tito.auth', 'throttle:tito'])->group(function () {
    Route::get('/tito/validate', [TitoController::class, 'validate'])->name('tito.validate');
});

use App\Http\Controllers\Api\AnalyticsController;

Route::middleware(['auth', 'feature:advanced_analytics', 'role:system_admin|legal|finance|audit'])->prefix('analytics')->group(function () {
    Route::get('/pipeline', [AnalyticsController::class, 'pipeline']);
    Route::get('/risk-distribution', [AnalyticsController::class, 'riskDistribution']);
    Route::get('/compliance-overview', [AnalyticsController::class, 'complianceOverview']);
    Route::get('/obligations-timeline', [AnalyticsController::class, 'obligationsTimeline']);
    Route::get('/ai-costs', [AnalyticsController::class, 'aiCosts']);
    Route::get('/workflow-performance', [AnalyticsController::class, 'workflowPerformance']);
});
