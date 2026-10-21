<?php
use App\Http\Controllers\PterohubOrdersController;
use Illuminate\Support\Facades\Route;

Route::prefix('ph-admin/orders')->middleware('auth')->group(function () {
    Route::get('/', [PterohubOrdersController::class,'index'])->name('pterohub.orders.index');
    Route::get('/create', [PterohubOrdersController::class,'create'])->name('pterohub.orders.create');
    Route::post('/', [PterohubOrdersController::class,'store'])->middleware('throttle:10,1')->name('pterohub.orders.store');
    Route::post('/{id}/confirm', [PterohubOrdersController::class,'confirm'])->whereNumber('id')->middleware('throttle:5,1')->name('pterohub.orders.confirm');
    Route::get('/{id}', [PterohubOrdersController::class,'show'])->whereNumber('id')->name('pterohub.orders.show');
});
