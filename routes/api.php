<?php

use App\Http\Controllers\Api\V1\AllahNameController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DhikrController;
use App\Http\Controllers\Api\V1\GuestIdentityController;
use App\Http\Controllers\Api\V1\IslamicCalendarController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\PasswordRecoveryController;
use App\Http\Controllers\Api\V1\PersonalWirdController;
use App\Http\Controllers\Api\V1\PrayerSettingController;
use App\Http\Controllers\Api\V1\PrayerTimesController;
use App\Http\Controllers\Api\V1\QiblaController;
use App\Http\Controllers\Api\V1\QuranAudioController;
use App\Http\Controllers\Api\V1\QuranAyahController;
use App\Http\Controllers\Api\V1\QuranBookmarkController;
use App\Http\Controllers\Api\V1\QuranFavoriteController;
use App\Http\Controllers\Api\V1\QuranPreferenceController;
use App\Http\Controllers\Api\V1\QuranReadingProgressController;
use App\Http\Controllers\Api\V1\QuranSurahController;
use App\Http\Controllers\Api\V1\SubcategoryController;
use Illuminate\Support\Facades\Route;

Route::pattern('wird', '[1-9][0-9]{0,17}');
Route::pattern('category', '[1-9][0-9]{0,17}');
Route::pattern('subcategory', '[1-9][0-9]{0,17}');
Route::pattern('dhikr', '[1-9][0-9]{0,17}');
Route::pattern('bookmark', '[1-9][0-9]{0,17}');
Route::pattern('favorite', '[1-9][0-9]{0,17}');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:asma-al-husna')->name('asma-al-husna.')->group(function (): void {
        Route::get('asma-al-husna', [AllahNameController::class, 'index'])->name('index');
        Route::get('asma-al-husna/{number}', [AllahNameController::class, 'show'])->where('number', '[1-9][0-9]?')->name('show');
    });
    Route::prefix('quran')->name('quran.')->middleware('throttle:quran')->group(function (): void {
        Route::get('surahs', [QuranSurahController::class, 'index'])->name('surahs.index');
        Route::get('surahs/{surah}', [QuranSurahController::class, 'show'])->where('surah', '[1-9][0-9]{0,2}')->name('surahs.show');
        Route::get('surahs/{surah}/ayahs', [QuranAyahController::class, 'index'])->where('surah', '[1-9][0-9]{0,2}')->name('ayahs.index');
        Route::get('surahs/{surah}/audio', [QuranAudioController::class, 'index'])->where('surah', '[1-9][0-9]{0,2}')->name('audio.index');
    });
    Route::get('prayer-times', PrayerTimesController::class)->middleware('throttle:prayer-times')->name('prayer-times');
    Route::get('islamic-calendar', IslamicCalendarController::class)->middleware('throttle:islamic-calendar')->name('islamic-calendar');
    Route::get('qibla', QiblaController::class)->middleware('throttle:qibla')->name('qibla');
    Route::middleware('throttle:content-api')->group(function (): void {
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::get('categories/{category}/subcategories', [SubcategoryController::class, 'index'])->name('categories.subcategories.index');
        Route::get('subcategories/{subcategory}', [SubcategoryController::class, 'show'])->name('subcategories.show');
        Route::get('subcategories/{subcategory}/dhikrs', [DhikrController::class, 'index'])->name('subcategories.dhikrs.index');
        Route::get('dhikrs/{dhikr}', [DhikrController::class, 'show'])->name('dhikrs.show');
    });
    Route::post('guest/identities', [GuestIdentityController::class, 'store'])->middleware('throttle:guest-identity')->name('guest.identities.store');
    Route::prefix('guest')->name('guest.')->middleware(['throttle:guest-api', 'guest.identity'])->group(function (): void {
        Route::apiResource('personal-wirds', PersonalWirdController::class)->parameters(['personal-wirds' => 'wird']);
        Route::post('personal-wirds/{wird}/increment', [PersonalWirdController::class, 'increment'])->name('personal-wirds.increment');
        Route::post('personal-wirds/{wird}/reset', [PersonalWirdController::class, 'reset'])->name('personal-wirds.reset');
    });
    Route::post('auth/register', [MobileAuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
    Route::post('auth/forgot-password', [PasswordRecoveryController::class, 'forgot'])->middleware('throttle:forgot-password')->name('auth.forgot-password');
    Route::post('auth/reset-password', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:reset-password')->name('auth.reset-password');
    Route::post('auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('auth/google', [MobileAuthController::class, 'google'])->middleware('throttle:google-login')->name('auth.google');

    Route::middleware(['auth:sanctum', 'mobile.token', 'account.active', 'throttle:api'])->group(function (): void {
        Route::prefix('quran')->name('quran.')->middleware('throttle:quran-tracking')->group(function (): void {
            Route::get('preferences', [QuranPreferenceController::class, 'show'])->name('preferences.show');
            Route::patch('preferences', [QuranPreferenceController::class, 'update'])->name('preferences.update');
            Route::apiResource('bookmarks', QuranBookmarkController::class)->except('update');
            Route::patch('bookmarks/{bookmark}', [QuranBookmarkController::class, 'update'])->name('bookmarks.update');
            Route::apiResource('favorites', QuranFavoriteController::class)->except('update');
            Route::get('reading-progress', [QuranReadingProgressController::class, 'show'])->name('reading-progress.show');
            Route::put('reading-progress', [QuranReadingProgressController::class, 'update'])->name('reading-progress.update');
            Route::delete('reading-progress', [QuranReadingProgressController::class, 'destroy'])->name('reading-progress.destroy');
        });
        Route::get('prayer-settings', [PrayerSettingController::class, 'show'])->name('prayer-settings.show');
        Route::patch('prayer-settings', [PrayerSettingController::class, 'update'])->middleware('throttle:prayer-settings-write')->name('prayer-settings.update');
        Route::post('auth/guest-claim', [GuestIdentityController::class, 'claim'])->middleware('throttle:guest-claim')->name('auth.guest-claim');
        Route::apiResource('personal-wirds', PersonalWirdController::class)->parameters(['personal-wirds' => 'wird']);
        Route::post('personal-wirds/{wird}/increment', [PersonalWirdController::class, 'increment'])->name('personal-wirds.increment');
        Route::post('personal-wirds/{wird}/reset', [PersonalWirdController::class, 'reset'])->name('personal-wirds.reset');
        Route::get('auth/me', [MobileAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [MobileAuthController::class, 'logoutAll'])->name('auth.logout-all');
    });
});

// Preserve the starter profile URL using the same secured controller.
Route::get('user', [MobileAuthController::class, 'me'])
    ->middleware(['auth:sanctum', 'mobile.token', 'account.active', 'throttle:api']);
