<?php
use App\Http\Controllers\PterohubBillingOverviewController;
use App\Http\Controllers\PterohubRenewalPlansController;
use Illuminate\Support\Facades\Route;
Route::middleware('auth')->prefix('ph-admin')->group(function(){
 Route::get('/billing',[PterohubBillingOverviewController::class,'index'])->name('pterohub.billing.index');
 Route::post('/services/{id}/renewal-plan',[PterohubRenewalPlansController::class,'store'])->whereNumber('id')->middleware('throttle:10,1')->name('pterohub.renewals.store');
 Route::post('/services/{id}/renewal-plan/cancel',[PterohubRenewalPlansController::class,'cancel'])->whereNumber('id')->middleware('throttle:10,1')->name('pterohub.renewals.cancel');
});
