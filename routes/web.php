<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HookController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

if (SetupController::needsSetup()) {
    Route::get('setup', [SetupController::class, 'view']);
    Route::post('setup', [SetupController::class, 'setup']);
    Route::fallback(function() {
        return redirect("/setup");
    });
    return;
}

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', [DashboardController::class, 'view'])->name('dashboard');
    Route::get('/newHook', [HookController::class, 'viewNew'])->name('newHook');
    Route::post('/newHook', [HookController::class, 'doNew']);
    Route::post('/hook/{hook}/deploy', [HookController::class, 'deploy']);
    Route::get('/hook/{hook}/edit', [HookController::class, 'viewEdit']);
    Route::post('/hook/{hook}/edit', [HookController::class, 'doEdit']);

    Route::get('setup', function() {
        return redirect('/');
    });
});

require __DIR__.'/auth.php';
