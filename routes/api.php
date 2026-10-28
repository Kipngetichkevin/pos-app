<?php

use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\MpesaCallbackController;
use App\Http\Middleware\AuthenticateBranch;
use Illuminate\Support\Facades\Route;

Route::post('/mpesa/stk/callback', [MpesaCallbackController::class, 'stk']);
Route::post('/payments/c2b/validation', [MpesaCallbackController::class, 'c2bValidation']);
Route::post('/payments/c2b/confirmation', [MpesaCallbackController::class, 'c2bConfirmation']);

// Cloud server: branch servers sync through these (POS_MODE=cloud only)
Route::middleware(AuthenticateBranch::class)->prefix('sync')->group(function () {
    Route::post('/push', [SyncController::class, 'push']);
    Route::get('/pull', [SyncController::class, 'pull']);
    Route::get('/mpesa', [SyncController::class, 'mpesa']);
});
