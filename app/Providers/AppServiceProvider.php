<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
use App\Policies\ContentPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** Register application services. */
    public function register(): void {}

    /** Register endpoint-specific limits without recording raw email addresses. */
    public function boot(): void
    {
        foreach (['prayer-times' => 60, 'islamic-calendar' => 60, 'qibla' => 30, 'quran' => 90, 'asma-al-husna' => 60] as $feature => $requests) {
            RateLimiter::for($feature, function (Request $request) use ($feature, $requests): Limit {
                $user = Auth::guard('sanctum')->user();

                return Limit::perMinute($requests)->by($feature.':'.($user ? 'user:'.$user->id : 'ip:'.$request->ip()));
            });
        }
        foreach ([Category::class, Subcategory::class, Dhikr::class] as $model) {
            Gate::policy($model, ContentPolicy::class);
        }
        RateLimiter::for('content-api', function (Request $request): Limit {
            $user = Auth::guard('sanctum')->user();

            return Limit::perMinute($user ? 120 : 60)->by($user ? 'content-user:'.$user->id : 'content-ip:'.$request->ip());
        });
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute($request->user() ? 120 : 60)
            ->by($request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));
        RateLimiter::for('admin-api', fn (Request $request): Limit => Limit::perMinute(120)->by('admin:'.$request->user()->id));
        RateLimiter::for('media-upload', fn (Request $request): Limit => Limit::perMinute(10)->by('media-upload:'.$request->user()->id));
        RateLimiter::for('prayer-settings-write', fn (Request $request): Limit => Limit::perMinute(30)->by('prayer-settings-write:'.$request->user()->id));
        RateLimiter::for('quran-tracking', function (Request $request): Limit {
            $read = $request->isMethod('GET') || $request->isMethod('HEAD');

            return Limit::perMinute($read ? 120 : 30)->by('quran-tracking:'.($read ? 'read:' : 'write:').$request->user()->id);
        });
        RateLimiter::for('guest-identity', fn (Request $request): Limit => Limit::perMinute(5)->by('guest-create:'.$request->ip()));
        RateLimiter::for('guest-api', fn (Request $request): Limit => Limit::perMinute(60)->by('guest-api:'.$request->ip()));
        RateLimiter::for('guest-claim', fn (Request $request): Limit => Limit::perMinute(5)->by('claim:'.$request->user()->id));
        RateLimiter::for('csrf-cookie', fn (Request $request): Limit => Limit::perMinute(60)->by('csrf:'.$request->ip()));
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(5)->by('register:'.$request->ip()));
        RateLimiter::for('google-login', fn (Request $request): Limit => Limit::perMinute(5)->by('google-login:'.$request->ip()));
        RateLimiter::for('reset-password', fn (Request $request): Limit => Limit::perMinute(5)->by('reset:'.$request->ip()));
        RateLimiter::for('forgot-password', function (Request $request): array {
            $email = $request->input('email');
            $identifier = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(3)->by('forgot:'.hash('sha256', $identifier.'|'.$request->ip())),
                Limit::perMinute(30)->by('forgot-ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $identifier = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by('credentials:'.hash('sha256', $identifier.'|'.$request->ip())),
                Limit::perMinute(60)->by('login-ip:'.$request->ip()),
            ];
        });
    }
}
