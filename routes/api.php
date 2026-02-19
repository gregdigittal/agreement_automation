<?php
use App\Http\Controllers\BoldsignWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/boldsign', [BoldsignWebhookController::class, 'handle'])->name('webhooks.boldsign');
