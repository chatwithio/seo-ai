<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleSearchConsoleAuthController;

use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\Auth\TemporaryUserLoginController;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/users/create', [UserAuthController::class, 'showRegister']);
Route::post('/users/create', [UserAuthController::class, 'register']);

Route::get('/users/login', [UserAuthController::class, 'showLogin'])->name('login');
Route::post('/users/login', [UserAuthController::class, 'login']);

Route::get('/users/temporary-login/{token}', TemporaryUserLoginController::class)
    ->middleware('throttle:10,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('users.temporary-login');

Route::post('/users/logout', [UserAuthController::class, 'logout'])->name('logout');
Route::get('/users/logout', [UserAuthController::class, 'logout']);

Route::get('/google/connect', [GoogleSearchConsoleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleSearchConsoleAuthController::class, 'callback']);
