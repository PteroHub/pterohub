<?php
// Include exactly once from routes/web.php via: require __DIR__.'/pterohub-v07.php';
use App\Http\Controllers\PterohubCatalogController;
use Illuminate\Support\Facades\Route;
Route::prefix('ph-admin')->middleware(['auth','throttle:30,1'])->name('pterohub.catalog.')->group(function () {
    Route::post('/games/{id}/edit', [PterohubCatalogController::class,'updateGame'])->name('game.update');
    Route::post('/plans/{id}/edit', [PterohubCatalogController::class,'updatePlan'])->name('plan.update');
    Route::post('/plans/{id}/prices', [PterohubCatalogController::class,'savePrices'])->name('plan.prices');
    Route::post('/sync', [PterohubCatalogController::class,'sync'])->middleware('throttle:2,5')->name('sync');
});
