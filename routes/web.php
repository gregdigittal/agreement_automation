<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\AzureAdController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/azure/redirect', [AzureAdController::class, 'redirect'])->name('azure.redirect');
Route::get('/auth/azure/callback', [AzureAdController::class, 'callback'])->name('azure.callback');
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

Route::get('/contracts/{contract}/download', function (\App\Models\Contract $contract) {
    $service = app(\App\Services\ContractFileService::class);
    $url = $service->getSignedUrl($contract->storage_path);
    return $url ? redirect($url) : abort(404);
})->middleware('auth')->name('contract.download');

// Vendor Portal Auth
Route::get('/vendor/login', fn () => view('vendor.login'))->name('vendor.login');
Route::post('/vendor/auth/request', [\App\Http\Controllers\VendorAuthController::class, 'requestLink'])->name('vendor.auth.request')->middleware('throttle:5,1');
Route::get('/vendor/auth/verify/{token}', [\App\Http\Controllers\VendorAuthController::class, 'verify'])->name('vendor.auth.verify');
Route::post('/vendor/logout', [\App\Http\Controllers\VendorAuthController::class, 'logout'])->name('vendor.logout');

Route::get('/vendor/contracts/{contract}/download', function (\App\Models\Contract $contract) {
    $user = auth('vendor')->user();
    if (!$user || $contract->counterparty_id !== $user->counterparty_id) abort(403);
    $service = app(\App\Services\ContractFileService::class);
    $url = $service->getSignedUrl($contract->storage_path);
    return $url ? redirect($url) : abort(404);
})->middleware('auth:vendor')->name('vendor.contract.download');

use App\Http\Controllers\Reports\ReportExportController;

Route::prefix('reports/export')->middleware('auth')->group(function () {
    Route::get('/contracts/excel', [ReportExportController::class, 'contractsExcel'])->name('reports.export.contracts.excel');
    Route::get('/contracts/pdf', [ReportExportController::class, 'contractsPdf'])->name('reports.export.contracts.pdf');
});
