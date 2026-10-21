<?php
use App\Http\Controllers\PterohubTicketsController;
use Illuminate\Support\Facades\Route;

Route::prefix('ph-admin')->middleware(['auth','throttle:60,1'])->group(function () {
    Route::get('/tickets', [PterohubTicketsController::class, 'index'])->name('pterohub.tickets.index');
    Route::get('/tickets/create', [PterohubTicketsController::class, 'create'])->name('pterohub.tickets.create');
    Route::post('/tickets', [PterohubTicketsController::class, 'store'])->name('pterohub.tickets.store')->middleware('throttle:10,1');
    Route::get('/tickets/{id}', [PterohubTicketsController::class, 'show'])->whereNumber('id')->name('pterohub.tickets.show');
    Route::post('/tickets/{id}/reply', [PterohubTicketsController::class, 'reply'])->whereNumber('id')->name('pterohub.tickets.reply')->middleware('throttle:10,1');
    Route::post('/tickets/{id}/status', [PterohubTicketsController::class, 'status'])->whereNumber('id')->name('pterohub.tickets.status')->middleware('throttle:20,1');
});
