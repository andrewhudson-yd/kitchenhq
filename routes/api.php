<?php

use App\Http\Controllers\AppShoppingController;
use App\Http\Middleware\AuthenticateAppToken;
use Illuminate\Support\Facades\Route;

// The phone app's offline screens. Signed in with a device token from POST /app/token.
Route::prefix('app')->middleware([AuthenticateAppToken::class, 'throttle:120,1'])->group(function () {
    Route::get('/shopping', [AppShoppingController::class, 'show']);
    Route::post('/shopping/sync', [AppShoppingController::class, 'sync']);
});
