<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute($request->user() ? 120 : 60)
            ->by($request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));
        RateLimiter::for('admin-api', fn (Request $request): Limit => Limit::perMinute(120)->by('admin:'.$request->user()->id));
        RateLimiter::for('csrf-cookie', fn (Request $request): Limit => Limit::perMinute(60)->by('csrf:'.$request->ip()));
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(5)->by('register:'.$request->ip()));
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
