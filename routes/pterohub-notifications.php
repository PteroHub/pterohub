<?php
use App\Http\Controllers\PterohubNotificationCenterController as Center;
use Illuminate\Support\Facades\Route;
Route::prefix('ph-admin')->middleware('auth')->group(function(){
 Route::get('/notifications',[Center::class,'index'])->name('pterohub.notifications.index');
 Route::post('/notifications/preferences',[Center::class,'save'])->middleware('throttle:ph-email-save')->name('pterohub.notifications.save');
 Route::get('/notifications/preview',[Center::class,'preview'])->middleware('throttle:30,1')->name('pterohub.notifications.preview');
 Route::post('/notifications/test',[Center::class,'test'])->middleware('throttle:ph-email-test')->name('pterohub.notifications.test');
 Route::post('/notifications/{id}/retry',[Center::class,'retry'])->whereNumber('id')->middleware('throttle:ph-email-test')->name('pterohub.notifications.retry');
});
