<?php

use App\Http\Controllers\Api\TitoController;
use App\Http\Controllers\Webhooks\BoldsignWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/boldsign', [BoldsignWebhookController::class, 'handle'])->name('webhooks.boldsign');

Route::middleware(['tito.auth', 'throttle:tito'])->group(function () {
    Route::get('/tito/validate', [TitoController::class, 'validate'])->name('tito.validate');
});

use App\Http\Controllers\Api\AnalyticsController;

Route::middleware(['auth:sanctum', 'feature:advanced_analytics', 'role:system_admin|legal|finance|audit'])->prefix('analytics')->group(function () {
    Route::get('/pipeline', [AnalyticsController::class, 'pipeline']);
    Route::get('/risk-distribution', [AnalyticsController::class, 'riskDistribution']);
    Route::get('/compliance-overview', [AnalyticsController::class, 'complianceOverview']);
    Route::get('/obligations-timeline', [AnalyticsController::class, 'obligationsTimeline']);
    Route::get('/ai-costs', [AnalyticsController::class, 'aiCosts']);
    Route::get('/workflow-performance', [AnalyticsController::class, 'workflowPerformance']);
});
