<?php
use App\Http\Controllers\PterohubEmailController;
use Illuminate\Support\Facades\Route;

// Independent email limits. Viewing settings never consumes a send-test budget.
Route::prefix('ph-admin')->middleware('auth')->group(function () {
    Route::get('/settings/email', [PterohubEmailController::class, 'index'])
        ->name('pterohub.email.index');
    Route::post('/settings/email/save', [PterohubEmailController::class, 'update'])
        ->middleware('throttle:ph-email-save')->name('pterohub.email.update');
    Route::post('/settings/email/test', [PterohubEmailController::class, 'test'])
        ->middleware('throttle:ph-email-test')->name('pterohub.email.test');
});
