<?php

use App\Http\Controllers\Api\TitoController;
use App\Http\Controllers\Webhooks\BoldsignWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/boldsign', [BoldsignWebhookController::class, 'handle'])->name('webhooks.boldsign');

Route::middleware('tito.auth')->group(function () {
    Route::get('/tito/validate', [TitoController::class, 'validate'])->name('tito.validate');
});
