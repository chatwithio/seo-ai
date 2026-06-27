<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleSearchConsoleAuthController;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/google/connect', [GoogleSearchConsoleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleSearchConsoleAuthController::class, 'callback']);
