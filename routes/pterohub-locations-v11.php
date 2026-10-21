<?php
\Illuminate\Support\Facades\Route::middleware(['auth','throttle:60,1'])->prefix('ph-admin')->name('pterohub.locations.')->controller(\App\Http\Controllers\PterohubLocationsController::class)->group(function () {
    \Illuminate\Support\Facades\Route::get('/locations', 'index')->name('index');
    \Illuminate\Support\Facades\Route::post('/locations/nodes/{id}/sale', 'updateNodeSale')->whereNumber('id')->name('node.sale');
    \Illuminate\Support\Facades\Route::post('/locations/products/{id}/nodes', 'updatePlanNodes')->whereNumber('id')->name('update');
});
