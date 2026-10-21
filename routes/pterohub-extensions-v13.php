<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PterohubExtensionsController;

Route::prefix('ph-admin/extensions')->middleware('auth')->group(function () {
    Route::post('/{id}/update', [PterohubExtensionsController::class, 'update'])
        ->whereNumber('id')->middleware('throttle:10,1')->name('pterohub.extensions.update');
    Route::post('/{id}/remove', [PterohubExtensionsController::class, 'remove'])
        ->whereNumber('id')->middleware('throttle:10,1')->name('pterohub.extensions.remove');
});
