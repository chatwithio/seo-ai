<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleSearchConsoleAuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/google/connect', [GoogleSearchConsoleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleSearchConsoleAuthController::class, 'callback']);
