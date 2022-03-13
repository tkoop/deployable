<?php

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
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('setup', function() {
        return redirect('/');
    });
});

require __DIR__.'/auth.php';
