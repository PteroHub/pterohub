<?php

use App\Http\Controllers\PterohubSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('ph-admin/settings')->middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/', [PterohubSettingsController::class, 'index'])->name('pterohub.settings.index');
    Route::get('/section/{group}', [PterohubSettingsController::class, 'show'])->name('pterohub.settings.group');
    Route::post('/section/{group}', [PterohubSettingsController::class, 'update'])->middleware('throttle:10,1')->name('pterohub.settings.save');
});
