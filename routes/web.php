<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleSearchConsoleAuthController;

use App\Http\Controllers\Auth\UserAuthController;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/users/create', [UserAuthController::class, 'showRegister']);
Route::post('/users/create', [UserAuthController::class, 'register']);

Route::get('/users/login', [UserAuthController::class, 'showLogin'])->name('login');
Route::post('/users/login', [UserAuthController::class, 'login']);

Route::post('/users/logout', [UserAuthController::class, 'logout'])->name('logout');
Route::get('/users/logout', [UserAuthController::class, 'logout']);

Route::get('/google/connect', [GoogleSearchConsoleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleSearchConsoleAuthController::class, 'callback']);
