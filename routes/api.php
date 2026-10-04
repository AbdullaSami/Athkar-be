<?php

use App\Http\Controllers\Api\V1\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/register', [MobileAuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
    Route::post('auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::middleware(['auth:sanctum', 'mobile.token', 'account.active', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [MobileAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [MobileAuthController::class, 'logoutAll'])->name('auth.logout-all');
    });
});

// Preserve the starter profile URL using the same secured controller.
Route::get('user', [MobileAuthController::class, 'me'])
    ->middleware(['auth:sanctum', 'mobile.token', 'account.active', 'throttle:api']);
