<?php

use App\AccountStatus;
use App\Models\User;
use App\Models\WorshipIdentity;
use App\Services\GuestIdentityService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['services.google.client_ids' => ['backend.apps.googleusercontent.com', 'android.apps.googleusercontent.com']]);
    Cache::flush();
    Http::preventStrayRequests();
});

/** @return array{private: string, public: string} Isolated RSA material; no production credential. */
function googleTestKeys(): array
{
    static $keys;
    if ($keys === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $keys = ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
    }

    return $keys;
}

/** @return array<string, mixed> Default valid Google claims with explicit test overrides. */
function googleTestClaims(array $overrides = []): array
{
    return array_replace([
        'iss' => 'https://accounts.google.com', 'aud' => 'backend.apps.googleusercontent.com',
        'azp' => 'android.apps.googleusercontent.com', 'sub' => '123456789012345678901',
        'iat' => time() - 10, 'exp' => time() + 3600,
        'email' => 'person@gmail.com', 'email_verified' => true, 'name' => 'Test Person',
    ], $overrides);
}

/** Sign a credential and fake only Google's public certificate endpoint. */
function googleTestToken(array $overrides = []): string
{
    Http::fake(['https://www.googleapis.com/oauth2/v1/certs' => Http::response(
        ['test-key' => googleTestKeys()['public']], 200, ['Cache-Control' => 'public, max-age=3600'],
    )]);

    return JWT::encode(googleTestClaims($overrides), googleTestKeys()['private'], 'RS256', 'test-key');
}

test('Google sign-in creates a passwordless account and a usable bounded mobile token', function () {
    $idToken = googleTestToken(['email' => 'PERSON@gmail.com']);
    $response = $this->postJson('/api/v1/auth/google', ['id_token' => $idToken, 'device_name' => 'Android', 'age' => 25])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.user.email', 'person@gmail.com')->assertJsonPath('data.token_type', 'Bearer');

    $user = User::sole();
    expect($user->google_id)->toBe('123456789012345678901');
    expect($user->password)->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->age)->toBe(25);
    expect($user->worshipIdentities()->count())->toBe(1);
    expect($user->roles()->count())->toBe(0);
    expect($user->tokens()->sole()->abilities)->toBe(['mobile']);
    expect($user->tokens()->sole()->expires_at)->not->toBeNull();
    expect($response->json('data.user'))->not->toHaveKeys(['password', 'google_id', 'remember_token']);
    $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://www.googleapis.com/oauth2/v1/certs' && ! str_contains($request->url(), $idToken));
});

test('Google sign-in uses the immutable subject and keeps existing profile and identity', function () {
    $user = User::factory()->google()->create(['google_id' => '123456789012345678901', 'email' => 'original@gmail.com']);
    WorshipIdentity::factory()->for($user)->create();
    $token = googleTestToken(['email' => 'changed@gmail.com']);

    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'iOS'])->assertOk()->assertJsonPath('data.user.id', $user->id);
    expect($user->fresh()->email)->toBe('original@gmail.com');
    $this->assertDatabaseCount('users', 1);
    expect($user->worshipIdentities()->count())->toBe(1);
    Http::assertSentCount(1);
});

test('Google sign-in refuses automatic email linking with HTTP 422', function (bool $googleAccount) {
    $factory = User::factory();
    $user = ($googleAccount ? $factory->google() : $factory)->create(['email' => 'person@gmail.com']);
    $previousSubject = $user->google_id;
    $token = googleTestToken();

    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android', 'age' => 25])
        ->assertUnprocessable()->assertJsonValidationErrors('id_token');
    expect($user->fresh()->google_id)->toBe($previousSubject);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    Http::assertSentCount(1);
})->with(['local account' => false, 'other Google subject' => true]);

test('a new Google account requires age and creates nothing when missing', function () {
    $token = googleTestToken();
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android'])
        ->assertUnprocessable()->assertJsonValidationErrors('age');
    $this->assertDatabaseCount('users', 0);
    Http::assertSentCount(1);
});

test('suspended Google accounts receive HTTP 422 without tokens', function () {
    $user = User::factory()->google()->create(['google_id' => '123456789012345678901', 'account_status' => AccountStatus::Suspended]);
    $token = googleTestToken();
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android'])
        ->assertUnprocessable()->assertJsonValidationErrors('id_token');
    expect($user->tokens()->count())->toBe(0);
    Http::assertSentCount(1);
});

test('Google registration claims guest data atomically', function () {
    $guest = app(GuestIdentityService::class)->create();
    $token = googleTestToken();
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android', 'age' => 25, 'guest_token' => $guest['guest_token']])->assertOk();
    $this->assertDatabaseHas('worship_identities', ['public_id' => $guest['identity_id'], 'user_id' => User::sole()->id, 'guest_token_hash' => null]);
    $this->assertDatabaseCount('worship_identities', 1);
    Http::assertSentCount(1);
});

test('invalid guest proof rolls back Google registration and tokens with HTTP 401', function () {
    $token = googleTestToken();
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android', 'age' => 25, 'guest_token' => str_repeat('x', 64)])->assertUnauthorized();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    Http::assertSentCount(1);
});

test('third party Google email addresses are not marked verified locally', function () {
    $token = googleTestToken(['email' => 'person@example.com']);
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android', 'age' => 25])->assertOk();
    expect(User::sole()->email_verified_at)->toBeNull();
    Http::assertSentCount(1);
});

test('invalid Google claims receive HTTP 422 without creating accounts', function (array $claims) {
    $token = googleTestToken($claims);
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android', 'age' => 25])
        ->assertUnprocessable()->assertJsonPath('errors.id_token.0', 'The Google credential is invalid.');
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    Http::assertSentCount(1);
})->with([
    'wrong audience' => [['aud' => 'attacker']],
    'array audience' => [['aud' => ['backend.apps.googleusercontent.com']]],
    'wrong issuer' => [['iss' => 'https://attacker.example']],
    'wrong presenter' => [['azp' => 'attacker']],
    'expired' => [['exp' => 1]],
    'missing expiration' => [['exp' => null]],
    'missing issued time' => [['iat' => null]],
    'string expiration' => [['exp' => '9999999999']],
    'future issued' => [['iat' => PHP_INT_MAX]],
    'future not before' => [['nbf' => PHP_INT_MAX]],
    'unverified email' => [['email_verified' => false]],
    'string verification' => [['email_verified' => 'true']],
    'malformed email' => [['email' => ['person@gmail.com']]],
    'invalid email' => [['email' => 'invalid']],
    'missing subject' => [['sub' => null]],
    'array subject' => [['sub' => ['123']]],
    'long subject' => [['sub' => str_repeat('a', 256)]],
    'missing profile name' => [['name' => null]],
]);

test('forged signatures unknown keys and algorithm confusion receive HTTP 422', function (string $attack) {
    googleTestToken();
    $token = match ($attack) {
        'signature' => JWT::encode(googleTestClaims(), openssl_pkey_new(['private_key_bits' => 2048]), 'RS256', 'test-key'),
        'key' => JWT::encode(googleTestClaims(), googleTestKeys()['private'], 'RS256', 'unknown-key'),
        'algorithm' => JWT::encode(googleTestClaims(), googleTestKeys()['public'], 'HS256', 'test-key'),
    };
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android', 'age' => 25])->assertUnprocessable()->assertJsonValidationErrors('id_token');
    $this->assertDatabaseCount('users', 0);
    Http::assertSentCount(1);
})->with(['signature', 'key', 'algorithm']);

test('Google provider failure receives safe HTTP 503', function (string $failure) {
    $token = JWT::encode(googleTestClaims(), googleTestKeys()['private'], 'RS256', 'test-key');
    Http::fake(['https://www.googleapis.com/oauth2/v1/certs' => match ($failure) {
        'connection' => Http::failedConnection(),
        'server' => Http::response('private provider error', 500),
        'client' => Http::response([], 403),
        'json' => Http::response('not json', 200),
        'empty' => Http::response([], 200),
        'key' => Http::response(['test-key' => 'not a key'], 200),
    }]);
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android', 'age' => 25])
        ->assertStatus(503)->assertExactJson(['message' => 'Server error.']);
    $this->assertDatabaseCount('users', 0);
    Http::assertSentCount(1);
})->with(['connection', 'server', 'client', 'json', 'empty', 'key']);

test('unconfigured Google sign-in returns HTTP 503 before calling Google', function () {
    config(['services.google.client_ids' => []]);
    $this->postJson('/api/v1/auth/google', ['id_token' => 'abc.def.ghi', 'device_name' => 'Android'])->assertStatus(503);
    Http::assertNothingSent();
});

test('Google signing certificates are cached until their provider lifetime expires', function () {
    $token = googleTestToken();
    $user = User::factory()->google()->create(['google_id' => '123456789012345678901']);
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android'])->assertOk();
    }
    expect($user->tokens()->count())->toBe(2);
    Http::assertSentCount(1);
});

test('expired signing certificate cache fetches rotated keys', function () {
    $this->freezeTime();
    $token = JWT::encode(googleTestClaims(), googleTestKeys()['private'], 'RS256', 'test-key');
    Http::fake(['https://www.googleapis.com/oauth2/v1/certs' => Http::sequence()
        ->push(['test-key' => googleTestKeys()['public']], 200, ['Cache-Control' => 'max-age=1'])
        ->push(['rotated-key' => googleTestKeys()['public']], 200, ['Cache-Control' => 'max-age=3600'])]);
    User::factory()->google()->create(['google_id' => '123456789012345678901']);
    $this->postJson('/api/v1/auth/google', ['id_token' => $token, 'device_name' => 'Android'])->assertOk();
    $this->travel(2)->seconds();
    $rotated = JWT::encode(googleTestClaims(), googleTestKeys()['private'], 'RS256', 'rotated-key');
    $this->postJson('/api/v1/auth/google', ['id_token' => $rotated, 'device_name' => 'Android'])->assertOk();
    Http::assertSentCount(2);
});

test('Google requests reject invalid and unexpected input with HTTP 422 before provider access', function (array $payload, string $field) {
    $this->postJson('/api/v1/auth/google', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([
    'missing' => [[], 'id_token'],
    'array token' => [['id_token' => ['abc'], 'device_name' => 'Android'], 'id_token'],
    'trimmed token' => [['id_token' => ' abc.def.ghi ', 'device_name' => 'Android'], 'id_token'],
    'oversized token' => [['id_token' => str_repeat('a', 8200).'.b.c', 'device_name' => 'Android'], 'id_token'],
    'missing device' => [['id_token' => 'abc.def.ghi'], 'device_name'],
    'age range' => [['id_token' => 'abc.def.ghi', 'device_name' => 'Android', 'age' => 121], 'age'],
    'identity injection' => [['id_token' => 'abc.def.ghi', 'device_name' => 'Android', 'google_id' => 'attacker'], 'google_id'],
]);

test('Google sign-in is limited to five attempts per minute with HTTP 429', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/google', [])->assertUnprocessable();
    }
    $this->postJson('/api/v1/auth/google', [])->assertTooManyRequests()->assertHeader('Retry-After');
    Http::assertNothingSent();
});
