<?php

use App\Models\User;
use App\Models\WorshipIdentity;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

test('guest identity creation accepts localhost frontend headers without a CSRF token or session', function (string $header) {
    $this->app->instance('env', 'local');
    config(['sanctum.stateful' => ['localhost:3000']]);

    $response = $this->withHeader($header, 'http://localhost:3000')
        ->postJson('/api/v1/guest/identities');

    $response->assertCreated()->assertJsonStructure(['data' => ['identity_id', 'guest_token']])
        ->assertCookieMissing('XSRF-TOKEN')->assertCookieMissing(config('session.cookie'));
    $this->assertDatabaseHas(WorshipIdentity::class, [
        'public_id' => $response->json('data.identity_id'),
        'guest_token_hash' => hash('sha256', $response->json('data.guest_token')),
        'user_id' => null,
    ]);
})->with(['Origin', 'Referer']);

test('mobile Bearer token writes accept a localhost origin without a CSRF token', function () {
    $this->app->instance('env', 'local');
    config(['sanctum.stateful' => ['localhost:3000']]);
    $user = User::factory()->create();
    $token = $user->createToken('mobile', ['mobile']);

    $response = $this->withHeader('Origin', 'http://localhost:3000')
        ->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout');

    $response->assertNoContent()->assertCookieMissing('XSRF-TOKEN')
        ->assertCookieMissing(config('session.cookie'));
    $this->assertModelMissing($token->accessToken);
});

test('CORS allows credentialed requests only from the configured dashboard origin', function (string $origin, bool $allowed) {
    config(['cors.allowed_origins' => ['https://dashboard.example']]);

    $response = $this->withHeaders([
        'Origin' => $origin, 'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type,x-xsrf-token',
    ])->options('/api/v1/admin/auth/login');

    $response->assertNoContent();
    if ($allowed) {
        $response->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    } else {
        expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe($origin)->not->toBe('*');
    }
})->with([
    'dashboard' => ['https://dashboard.example', true],
    'untrusted site' => ['https://attacker.example', false],
]);

test('API errors never reveal exceptions even when debugging is enabled', function () {
    config(['app.debug' => true]);
    Route::middleware('api')->get('/api/v1/test-failure', function (): void {
        throw new RuntimeException('private database password marker');
    });

    $this->getJson('/api/v1/test-failure')->assertInternalServerError()->assertExactJson(['message' => 'Server error.']);
});

test('API requests without an Accept header still receive safe JSON errors', function () {
    $this->get('/api/v1/auth/me')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
});

test('the mobile limiter allows 120 requests per user and returns 429 on the next request', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    for ($request = 0; $request < 120; $request++) {
        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    $this->getJson('/api/v1/auth/me')->assertTooManyRequests()->assertHeader('Retry-After');
});

test('CSRF cookie requests return 429 after sixty requests from the same IP', function () {
    for ($request = 0; $request < 60; $request++) {
        $this->getJson('/sanctum/csrf-cookie')->assertNoContent();
    }

    $this->getJson('/sanctum/csrf-cookie')->assertTooManyRequests()->assertHeader('Retry-After');
});
