<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/contracts/{contract}/download', function (\App\Models\Contract $contract) {
    $service = app(\App\Services\ContractFileService::class);
    $url = $service->getSignedUrl($contract);
    return $url ? redirect($url) : abort(404);
})->middleware('auth')->name('contract.download');

// Vendor Portal Auth
Route::get('/vendor/login', fn () => view('vendor.login'))->name('vendor.login');
Route::post('/vendor/auth/request', [\App\Http\Controllers\VendorAuthController::class, 'requestLink'])->name('vendor.auth.request');
Route::get('/vendor/auth/verify/{token}', [\App\Http\Controllers\VendorAuthController::class, 'verify'])->name('vendor.auth.verify');
Route::post('/vendor/logout', [\App\Http\Controllers\VendorAuthController::class, 'logout'])->name('vendor.logout');

Route::get('/vendor/contracts/{contract}/download', function (\App\Models\Contract $contract) {
    $user = auth('vendor')->user();
    if (!$user || $contract->counterparty_id !== $user->counterparty_id) abort(403);
    $service = app(\App\Services\ContractFileService::class);
    $url = $service->getSignedUrl($contract);
    return $url ? redirect($url) : abort(404);
})->middleware('auth:vendor')->name('vendor.contract.download');

use App\Http\Controllers\Reports\ReportExportController;

Route::prefix('reports/export')->middleware('auth')->group(function () {
    Route::get('/contracts/excel', [ReportExportController::class, 'contractsExcel'])->name('reports.export.contracts.excel');
    Route::get('/contracts/pdf', [ReportExportController::class, 'contractsPdf'])->name('reports.export.contracts.pdf');
});
