<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PterohubInfrastructureController;
use App\Http\Controllers\PterohubExtensionsController;
Route::prefix('ph-admin')->middleware('auth')->group(function () {
 Route::get('/capacity',[PterohubInfrastructureController::class,'index'])->name('pterohub.capacity.index');
 Route::get('/extensions',[PterohubExtensionsController::class,'index'])->name('pterohub.extensions.index');
 Route::post('/extensions/register',[PterohubExtensionsController::class,'register'])->middleware('throttle:5,1')->name('pterohub.extensions.register');
});
