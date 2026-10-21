<?php
use App\Http\Controllers\PterohubClientController as Client;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/games', [Client::class,'catalog'])->name('ph.client.catalog');
Route::get('/games/{game}/{plan}', [Client::class,'plan'])->name('ph.client.plan');
// Public session cart; all writes require Laravel's CSRF middleware.
Route::get('/cart', [\App\Http\Controllers\PterohubCartController::class, 'show'])->name('ph.cart.show');
Route::post('/cart/add', [\App\Http\Controllers\PterohubCartController::class, 'add'])->middleware('throttle:20,1')->name('ph.cart.add');
Route::post('/cart/update', [\App\Http\Controllers\PterohubCartController::class, 'update'])->middleware('throttle:30,1')->name('ph.cart.update');
Route::post('/cart/remove', [\App\Http\Controllers\PterohubCartController::class, 'remove'])->middleware('throttle:30,1')->name('ph.cart.remove');
Route::prefix('client')->name('ph.client.')->group(function(){
    // Login must remain accessible even when the admin is authenticated.
    // This allows switching to a customer account without a guest redirect.
    Route::get('/login',[Client::class,'loginForm'])->name('login');
    Route::post('/login',[Client::class,'login'])->middleware('throttle:5,1')->name('login.submit');
    Route::middleware('guest')->group(function(){
        Route::get('/register',[Client::class,'registerForm'])->name('register');
        Route::post('/register',[Client::class,'register'])->middleware('throttle:3,10')->name('register.submit');
        Route::get('/forgot-password',[Client::class,'forgotForm'])->name('password.request');
        Route::post('/forgot-password',[Client::class,'forgot'])->middleware('throttle:3,10')->name('password.email');
        Route::post('/reset-password',[Client::class,'reset'])->middleware('throttle:5,1')->name('password.update');
    });
    Route::middleware('auth')->group(function(){
        Route::post('/logout',[Client::class,'logout'])->name('logout');
        Route::middleware(['verified', 'client.only'])->group(function(){
            Route::get('/',[Client::class,'dashboard'])->name('dashboard');
            Route::get('/getting-started',[Client::class,'gettingStarted'])->name('getting-started');
            Route::get('/profile',[Client::class,'profile'])->name('profile');
            Route::post('/profile',[Client::class,'profileSave'])->name('profile.save');
            Route::get('/security',[Client::class,'security'])->name('security');
            Route::post('/security/password',[Client::class,'passwordUpdate'])->middleware('throttle:5,1')->name('security.password');
            Route::get('/orders',[Client::class,'orders'])->name('orders');
            Route::get('/orders/{id}',[Client::class,'orderShow'])->whereNumber('id')->name('orders.show');
            Route::post('/orders/{id}/cancel',[Client::class,'cancelDraftOrder'])->whereNumber('id')->middleware('throttle:5,1')->name('orders.cancel');
            Route::get('/services',[Client::class,'services'])->name('services');
            Route::get('/services/{id}',[Client::class,'serviceShow'])->whereNumber('id')->name('services.show');
            Route::get('/invoices',[Client::class,'invoices'])->name('invoices');
            Route::get('/invoices/{id}',[Client::class,'invoiceShow'])->whereNumber('id')->name('invoices.show');
            Route::get('/tickets',[Client::class,'tickets'])->name('tickets');
            Route::get('/tickets/create',[Client::class,'ticketCreate'])->name('tickets.create');
            Route::post('/tickets',[Client::class,'ticketStore'])->middleware('throttle:5,1')->name('tickets.store');
            Route::get('/tickets/{id}',[Client::class,'ticketShow'])->name('tickets.show');
            Route::post('/tickets/{id}/reply',[Client::class,'ticketReply'])->middleware('throttle:10,1')->name('tickets.reply');
            Route::post('/checkout/{plan}',[Client::class,'checkout'])->middleware('throttle:5,1')->name('checkout');
            Route::post('/cart/checkout',[\App\Http\Controllers\PterohubCartController::class,'checkout'])->middleware('throttle:5,1')->name('cart.checkout');
        });
    });
});

// Laravel's built-in verification middleware and notification expect these exact route names.
Route::middleware('auth')->group(function () {
    Route::get('/client/verify-email', function () {
        abort_if((bool) auth()->user()->is_pterohub_admin, 403);
        return view('pterohub.client.verify');
    })->middleware('client.only')->name('verification.notice');
    Route::get('/client/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        abort_if((bool) auth()->user()->is_pterohub_admin, 403);
        $request->fulfill();
        // A remote account is created only after the customer verifies email ownership.
        $result = app(\App\Services\PterohubAccountSync::class)->sync((int)$request->user()->id);
        return redirect()->route('ph.client.dashboard')->with(
            $result['status']==='synced' ? 'ok' : 'error',
            $result['status']==='synced' ? 'Pterodactyl account synchronized.' : 'Your PteroHub account is verified. Pterodactyl account synchronization needs administrator attention.'
        );
    })->middleware(['client.only','signed','throttle:6,1'])->name('verification.verify');
    Route::post('/client/email/verification-notification', function (Request $request) {
        abort_if((bool) auth()->user()->is_pterohub_admin, 403);
        if (!$request->user()->hasVerifiedEmail()) $request->user()->sendEmailVerificationNotification();
        return back()->with('ok','Verification email requested.');
    })->middleware(['client.only','throttle:3,10'])->name('verification.send');
});

// Laravel password reset notification expects the global password.reset route name.
Route::get('/client/reset-password/{token}', [Client::class,'resetForm'])->middleware('guest')->name('password.reset');
