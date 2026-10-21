<?php
use App\Http\Controllers\PterohubInvoicesController;
use Illuminate\Support\Facades\Route;

Route::prefix('ph-admin/invoices')->middleware('auth')->group(function () {
    Route::get('/',[PterohubInvoicesController::class,'index'])->name('pterohub.invoices.index');
    Route::get('/create',[PterohubInvoicesController::class,'create'])->name('pterohub.invoices.create');
    Route::post('/',[PterohubInvoicesController::class,'store'])->middleware('throttle:10,1')->name('pterohub.invoices.store');
    Route::post('/{id}/issue',[PterohubInvoicesController::class,'issue'])->whereNumber('id')->middleware('throttle:10,1')->name('pterohub.invoices.issue');
    Route::post('/{id}/pay',[PterohubInvoicesController::class,'markPaid'])->whereNumber('id')->middleware('throttle:10,1')->name('pterohub.invoices.pay');
    Route::post('/{id}/cancel',[PterohubInvoicesController::class,'cancel'])->whereNumber('id')->middleware('throttle:10,1')->name('pterohub.invoices.cancel');
    Route::get('/{id}',[PterohubInvoicesController::class,'show'])->whereNumber('id')->name('pterohub.invoices.show');
});
