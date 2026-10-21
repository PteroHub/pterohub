<?php
use App\Http\Controllers\PterohubManualProvisioningController;
use Illuminate\Support\Facades\Route;
Route::middleware('auth')->prefix('ph-admin/provisioning')->name('pterohub.manual.')
    ->controller(PterohubManualProvisioningController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/link-user', 'link')->middleware('throttle:10,1')->name('link');
        Route::post('/review', 'review')->middleware('throttle:10,1')->name('review');
        Route::post('/reconcile', 'reconcile')->middleware('throttle:3,1')->name('reconcile');
        Route::post('/create', 'create')->middleware('throttle:3,1')->name('create');
    });
