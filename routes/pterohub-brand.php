<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PterohubBrandController;
Route::prefix('ph-admin')->middleware(['auth','throttle:60,1'])->group(function(){
 Route::get('/settings/branding',[PterohubBrandController::class,'edit'])->name('pterohub.brand.edit');
 Route::post('/settings/branding',[PterohubBrandController::class,'update'])->name('pterohub.brand.update')->middleware('throttle:10,1');
});
