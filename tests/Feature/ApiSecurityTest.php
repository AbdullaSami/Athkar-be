<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(LazilyRefreshDatabase::class);

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
