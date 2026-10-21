<?php
use App\Http\Controllers\PterohubAccountSyncController as Sync;
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','verified','client.only'])->prefix('client')->name('ph.client.account.')->group(function(){
 Route::post('/account/name',[Sync::class,'profile'])->middleware('throttle:6,1')->name('name');
 Route::post('/account/email',[Sync::class,'requestEmail'])->middleware('throttle:3,10')->name('email.request');
 Route::get('/account/email/confirm/{token}',[Sync::class,'confirmEmail'])->middleware(['signed','throttle:6,1'])->name('email.confirm');
});
Route::prefix('ph-admin/customers')->middleware(['auth','throttle:20,1'])->name('pterohub.customers.')->group(function(){
 Route::post('/{id}/account',[Sync::class,'adminSave'])->whereNumber('id')->name('account.save');
 Route::post('/{id}/sync',[Sync::class,'adminRetry'])->whereNumber('id')->name('sync');
});
