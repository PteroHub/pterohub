<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PterohubSandboxPaymentsController as C;
Route::post('/client/orders/{id}/sandbox-checkout',[C::class,'start'])->middleware(['auth','verified','client.only','throttle:3,10'])->whereNumber('id')->name('ph.sandbox.start');
Route::get('/client/sandbox/paypal/{attempt}/return',[C::class,'paypalReturn'])->middleware(['auth','verified','client.only','throttle:5,10'])->whereNumber('attempt')->name('ph.sandbox.paypal.return');
Route::get('/client/sandbox/{provider}/{attempt}/return',[C::class,'returned'])->middleware(['auth','client.only','throttle:15,1'])->whereNumber('attempt')->name('ph.sandbox.return');
Route::post('/client/sandbox/paypal/{attempt}/capture',[C::class,'paypalCapture'])->middleware(['auth','verified','client.only','throttle:3,10'])->whereNumber('attempt')->name('ph.sandbox.paypal.capture');
Route::post('/webhooks/pterohub/stripe-sandbox',[C::class,'stripeWebhook'])->name('ph.sandbox.stripe.webhook');
Route::post('/webhooks/pterohub/paypal-sandbox',[C::class,'paypalWebhook'])->name('ph.sandbox.paypal.webhook');
Route::get('/ph-admin/sandbox-payments',[C::class,'admin'])->middleware(['auth','throttle:30,1'])->name('pterohub.sandbox.admin');
