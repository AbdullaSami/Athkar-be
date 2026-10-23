<?php

use App\Http\Controllers\Api\V1\AdminAnalyticsController;
use App\Http\Controllers\Api\V1\AdminCategoryController;
use App\Http\Controllers\Api\V1\AdminDhikrController;
use App\Http\Controllers\Api\V1\AdminMediaController;
use App\Http\Controllers\Api\V1\AdminRoleController;
use App\Http\Controllers\Api\V1\AdminSubcategoryController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AdminUserOverviewController;
use App\Http\Controllers\Api\V1\DashboardAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/v1/admin')->name('api.v1.admin.')
    ->where(['user' => '[1-9][0-9]{0,17}', 'role' => '[1-9][0-9]{0,17}', 'category' => '[1-9][0-9]{0,17}', 'subcategory' => '[1-9][0-9]{0,17}', 'dhikr' => '[1-9][0-9]{0,17}'])
    ->middleware(['auth:sanctum', 'dashboard.session', 'account.active', 'can:access-dashboard', 'throttle:admin-api'])
    ->group(function (): void {
        Route::get('analytics', AdminAnalyticsController::class)->middleware('can:view-analytics')->name('analytics.show');
        Route::apiResource('roles', AdminRoleController::class);
        Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('permissions.index');
        Route::get('user-overview', [AdminUserOverviewController::class, 'show'])->name('user-overview.show');
        Route::apiResource('users', AdminUserController::class)->except('index');
        Route::put('users/{user}/roles', [AdminUserController::class, 'roles'])->name('users.roles.update');
        Route::get('users', [AdminUserOverviewController::class, 'users'])->name('users.index');
        Route::get('guests', [AdminUserOverviewController::class, 'guests'])->name('guests.index');
        Route::apiResource('categories', AdminCategoryController::class);
        Route::apiResource('subcategories', AdminSubcategoryController::class);
        Route::apiResource('dhikrs', AdminDhikrController::class);
        Route::post('media/icons', [AdminMediaController::class, 'icon'])->middleware('throttle:media-upload')->name('media.icons.store');
        Route::post('media/audio', [AdminMediaController::class, 'audio'])->middleware('throttle:media-upload')->name('media.audio.store');
    });

// The web group starts sessions and requires CSRF on browser writes, including login.
Route::prefix('api/v1/admin/auth')->name('api.v1.admin.auth.')->group(function (): void {
    Route::post('login', [DashboardAuthController::class, 'login'])->middleware('throttle:login')->name('login');

    Route::middleware(['auth:sanctum', 'dashboard.session', 'account.active', 'can:access-dashboard', 'throttle:admin-api'])->group(function (): void {
        Route::get('me', [DashboardAuthController::class, 'me'])->name('me');
        Route::post('logout', [DashboardAuthController::class, 'logout'])->name('logout');
    });
});
