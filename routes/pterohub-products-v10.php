<?php
// Loaded exactly once by routes/web.php; DO NOT duplicate the main Route import.
\Illuminate\Support\Facades\Route::prefix('ph-admin/products')
    ->middleware(['auth','throttle:60,1'])->name('pterohub.products.')
    ->controller(\App\Http\Controllers\PterohubProductsController::class)
    ->group(function () {
        \Illuminate\Support\Facades\Route::get('/', 'index')->name('index');
        \Illuminate\Support\Facades\Route::get('/create', 'create')->name('create');
        \Illuminate\Support\Facades\Route::post('/', 'store')->name('store');
        \Illuminate\Support\Facades\Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        \Illuminate\Support\Facades\Route::post('/{id}/edit', 'update')->whereNumber('id')->name('update');
        \Illuminate\Support\Facades\Route::post('/{id}/prices', 'prices')->whereNumber('id')->name('prices');
        \Illuminate\Support\Facades\Route::post('/{id}/publication', 'publication')->whereNumber('id')->name('publication');
        \Illuminate\Support\Facades\Route::get('/{id}/delete', 'confirmDelete')->whereNumber('id')->name('delete.confirm');
        \Illuminate\Support\Facades\Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });
