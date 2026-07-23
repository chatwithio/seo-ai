<?php

use App\Http\Controllers\Api\ContentFeedController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/content')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('/', [ContentFeedController::class, 'index']);
        Route::get('/unread', [ContentFeedController::class, 'nextUnread']);
    });
