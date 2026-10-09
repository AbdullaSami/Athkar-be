<?php

use App\Http\Middleware\AuthenticateGuestIdentity;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\RequireCsrfToken;
use App\Http\Middleware\RequireDashboardSession;
use App\Http\Middleware\RequireMobileToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            foreach (Route::getRoutes() as $route) {
                if ($route->getName() === 'sanctum.csrf-cookie') {
                    $route->middleware('throttle:csrf-cookie');
                }
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trimStrings(except: ['guest_token', 'token', 'id_token', 'text', 'source', 'virtue', 'description', 'explanation']);
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
        $middleware->web(append: [AuthenticateSession::class], replace: [PreventRequestForgery::class => RequireCsrfToken::class]);
        $middleware->alias([
            'guest.identity' => AuthenticateGuestIdentity::class,
            'account.active' => EnsureActiveAccount::class,
            'mobile.token' => RequireMobileToken::class,
            'dashboard.session' => RequireDashboardSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['guest_token', 'token', 'id_token']);
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $message = match ($status) {
                401 => 'Unauthenticated.',
                403 => 'Forbidden.',
                404 => 'Not found.',
                419 => 'CSRF token mismatch.',
                422 => 'The given data was invalid.',
                429 => 'Too many requests.',
                default => $status >= 500 ? 'Server error.' : (Response::$statusTexts[$status] ?? 'Request failed.'),
            };
            $data = ['message' => $message];

            if ($status === 422 && $exception instanceof ValidationException) {
                $data['errors'] = $exception->errors();
            }

            $headers = $response->headers->all();
            unset($headers['content-type'], $headers['content-length']);

            return response()->json($data, $status, $headers)->header('Cache-Control', 'no-store');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
