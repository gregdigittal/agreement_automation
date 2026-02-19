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
