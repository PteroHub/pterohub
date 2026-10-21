<?php
use App\Http\Controllers\PterohubServicesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('ph-admin/services')->name('pterohub.services.')
    ->controller(PterohubServicesController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::post('/{id}/sync', 'sync')->whereNumber('id')->middleware('throttle:4,1')->name('sync');
        Route::post('/{id}/renewal-preview', 'previewRenewal')->whereNumber('id')->middleware('throttle:6,1')->name('renewal.preview');
        Route::post('/{id}/verify', 'verify')->whereNumber('id')->middleware('throttle:6,1')->name('verify');
    });
