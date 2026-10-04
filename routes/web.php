<?php

use App\Http\Controllers\Api\V1\DashboardAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The web group starts sessions and requires CSRF on browser writes, including login.
Route::prefix('api/v1/admin/auth')->name('api.v1.admin.auth.')->group(function (): void {
    Route::post('login', [DashboardAuthController::class, 'login'])->middleware('throttle:login')->name('login');

    Route::middleware(['auth:sanctum', 'dashboard.session', 'account.active', 'can:access-dashboard', 'throttle:admin-api'])->group(function (): void {
        Route::get('me', [DashboardAuthController::class, 'me'])->name('me');
        Route::post('logout', [DashboardAuthController::class, 'logout'])->name('logout');
    });
});
