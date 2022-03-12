<?php

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

if (env("ADMIN_PASSWORD", null) == null) {
    Route::fallback(function () {
        return view('no-password');
    });
    return;
}

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('no-password', function() {
        return redirect('/');
    });
});

require __DIR__.'/auth.php';
