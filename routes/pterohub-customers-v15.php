<?php
\Illuminate\Support\Facades\Route::prefix('ph-admin/customers')->middleware(['auth','throttle:60,1'])
 ->name('pterohub.customers.')->controller(\App\Http\Controllers\PterohubCustomersController::class)
 ->group(function(){
  \Illuminate\Support\Facades\Route::get('/','index')->name('index');
  \Illuminate\Support\Facades\Route::get('/{id}','show')->whereNumber('id')->name('show');
  \Illuminate\Support\Facades\Route::post('/{id}/billing','save')->whereNumber('id')->name('billing.save');
 });
