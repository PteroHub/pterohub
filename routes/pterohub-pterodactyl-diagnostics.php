<?php

use App\Http\Controllers\PterohubPterodactylDiagnosticsController;
use Illuminate\Support\Facades\Route;

// The diagnostics page itself is not rate-limited. Each POST uses a separate,
// authenticated limiter; do not stack a group-wide throttle on these routes.
Route::middleware('auth')->prefix('ph-admin/pterodactyl')
    ->name('pterohub.pterodactyl.')
    ->controller(PterohubPterodactylDiagnosticsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/test-connection', 'testConnection')
            ->middleware('throttle:ph-pterodactyl-connection')->name('test');
        Route::post('/test-node', 'testNode')
            ->middleware('throttle:ph-pterodactyl-allocations')->name('node');
        Route::post('/preflight', 'preflight')
            ->middleware('throttle:ph-pterodactyl-capacity')->name('preflight');
        Route::post('/check-reservations', 'checkReservations')
            ->middleware('throttle:ph-pterodactyl-capacity')->name('reservations');
        Route::post('/check-order-item', 'checkOrderItem')
            ->middleware('throttle:ph-pterodactyl-capacity')->name('order-readiness');
        Route::post('/check-capacity', 'checkCapacity')
            ->middleware('throttle:ph-pterodactyl-capacity')->name('capacity');
    });
