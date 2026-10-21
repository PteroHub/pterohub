<?php

use Illuminate\Support\Facades\Route;
Route::get('/', [\App\Http\Controllers\PterohubPublicController::class, 'home'])->name('ph.home');
Route::get('/help', fn () => view('pterohub.public.help'))->name('ph.help');

// Add to the BOTTOM of your existing routes/web.php (do not replace existing routes).
use App\Http\Controllers\PterohubAdminController;
Route::middleware('guest')->group(function () {
    Route::get('/ph-admin/login', [PterohubAdminController::class, 'loginForm'])->name('pterohub.login');
    Route::post('/ph-admin/login', [PterohubAdminController::class, 'login'])->middleware('throttle:5,1')->name('pterohub.login.submit');
});
Route::prefix('ph-admin')->middleware('auth')->group(function () {
    Route::post('/logout', [PterohubAdminController::class, 'logout'])->name('pterohub.logout');
    Route::get('/', [PterohubAdminController::class, 'dashboard'])->name('pterohub.admin.dashboard');
    Route::post('/games', [PterohubAdminController::class, 'game'])->name('pterohub.admin.game');
    Route::post('/eggs/{egg}/assign', [PterohubAdminController::class, 'assignEgg'])->name('pterohub.admin.assign');
    Route::post('/plans', [PterohubAdminController::class, 'plan'])->name('pterohub.admin.plan');
});

require __DIR__.'/pterohub-v07.php';

require __DIR__.'/pterohub-products-v10.php';

require __DIR__.'/pterohub-locations-v11.php';

require __DIR__.'/pterohub-v12.php';

require __DIR__.'/pterohub-extensions-v13.php';

require __DIR__.'/pterohub-customers-v15.php';

require __DIR__.'/pterohub-orders-v16.php';

require __DIR__.'/pterohub-invoices-v17.php';

require __DIR__.'/pterohub-v18.php';

require __DIR__.'/pterohub-brand.php';

require __DIR__.'/pterohub-email-v19.php';

require __DIR__.'/pterohub-client.php';

require __DIR__.'/pterohub-settings.php';

require __DIR__.'/pterohub-pterodactyl-diagnostics.php';

require __DIR__.'/pterohub-manual-provisioning.php';

require __DIR__.'/pterohub-services.php';

require __DIR__.'/pterohub-notifications.php';

require __DIR__.'/pterohub-account-sync.php';

require __DIR__.'/pterohub-billing.php';

require __DIR__.'/pterohub-sandbox-payments.php';
