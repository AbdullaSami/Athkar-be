<?php

use App\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(LazilyRefreshDatabase::class);

/** @return array<string, mixed> Valid mobile registration input. */
function mobileRegistrationPayload(): array
{
    return [
        'name' => '  أحمد  ', 'email' => '  AHMED@example.com  ', 'age' => 24,
        'password' => '  secure password  ', 'password_confirmation' => '  secure password  ',
        'device_name' => 'iPhone',
    ];
}

test('registration creates an active account and a hashed expiring mobile token', function () {
    $payload = mobileRegistrationPayload();

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertCreated()->assertJsonPath('data.user.name', 'أحمد')
        ->assertJsonPath('data.user.email', 'ahmed@example.com')
        ->assertJsonPath('data.token_type', 'Bearer')->assertHeader('Cache-Control', 'no-store, private');
    $user = User::sole();
    $token = PersonalAccessToken::findToken($response->json('data.token'));
    expect(Hash::check($payload['password'], $user->password))->toBeTrue();
    expect(Hash::check(trim($payload['password']), $user->password))->toBeFalse();
    expect($user->worshipIdentities()->count())->toBe(1);
    expect($token->abilities)->toBe(['mobile']);
    expect($token->expires_at)->not->toBeNull();
    expect($response->json('data.user'))->not->toHaveKeys(['password', 'remember_token', 'google_id']);
    $this->assertGuest('web');
});

test('registration returns 422 for missing account fields without creating an account', function () {
    $this->postJson('/api/v1/auth/register', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'age', 'password', 'password_confirmation', 'device_name']);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('registration returns 422 for invalid or unsupported fields', function (string $field, mixed $value, ?string $errorField = null) {
    $payload = mobileRegistrationPayload();
    $payload[$field] = $value;
    if ($field === 'password') {
        $payload['password_confirmation'] = $value;
    }

    $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors($errorField ?? $field);

    $this->assertDatabaseCount('users', 0);
})->with([
    'age below range' => ['age', 0], 'age above range' => ['age', 121],
    'fractional age' => ['age', 24.5], 'invalid email' => ['email', 'invalid'],
    'oversized name' => ['name', str_repeat('x', 161)],
    'oversized device name' => ['device_name', str_repeat('x', 101)],
    'invalid phone' => ['phone', 'not a phone'],
    'short password' => ['password', 'short'],
    'password mismatch' => ['password_confirmation', 'different password', 'password'],
    'null byte password' => ['password', "invalid\0password"],
    'bcrypt byte overflow' => ['password', str_repeat('ع', 37)],
    'privilege injection' => ['account_status', 'active'],
    'role injection' => ['roles', ['admin']], 'provider identity injection' => ['google_id', 'fake'],
]);

test('registration rejects normalized duplicate emails', function () {
    User::factory()->create(['email' => 'ahmed@example.com']);

    $this->postJson('/api/v1/auth/register', mobileRegistrationPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('users', 1);
});

test('login accepts normalized email and returns an authenticated owner profile', function () {
    $user = User::factory()->create(['email' => 'ahmed@example.com']);

    $response = $this->postJson('/api/v1/auth/login', ['email' => ' AHMED@example.com ', 'password' => 'password', 'device_name' => 'Android']);

    $response->assertOk()->assertJsonPath('data.user.id', $user->id);
    $this->assertGuest('web');
    Auth::forgetGuards();
    $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')
        ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
});

test('login gives the same 422 response for invalid, Google-only and suspended accounts', function (string $kind) {
    $user = match ($kind) {
        'google' => User::factory()->google()->create(),
        'suspended' => User::factory()->create(['account_status' => AccountStatus::Suspended]),
        default => User::factory()->create(),
    };
    $password = $kind === 'invalid' ? 'wrong password' : 'password';

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password, 'device_name' => 'Android'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertGuest('web');
})->with(['invalid', 'google', 'suspended']);

test('profile returns 401 for missing, invalid or expired tokens', function (string $kind) {
    if ($kind === 'expired') {
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['mobile'], now()->subMinute())->plainTextToken;
        $this->withToken($token);
    } elseif ($kind === 'invalid') {
        $this->withToken('invalid');
    }

    $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
})->with(['missing', 'invalid', 'expired']);

test('a suspended account cannot use an existing token', function () {
    $user = User::factory()->create(['account_status' => AccountStatus::Suspended]);
    $token = $user->createToken('mobile', ['mobile'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
});

test('mobile endpoints reject browser session authentication and unscoped tokens', function (string $kind) {
    $user = User::factory()->create();
    if ($kind === 'session') {
        $this->actingAs($user, 'web');
    } else {
        $this->withToken($user->createToken('other', ['other'])->plainTextToken);
    }

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus($kind === 'session' ? 401 : 403);
})->with(['session', 'unscoped']);

test('logout revokes only the presented token and keeps other devices authenticated', function () {
    $user = User::factory()->create();
    $current = $user->createToken('current', ['mobile']);
    $other = $user->createToken('other', ['mobile']);

    $this->withToken($current->plainTextToken)->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->assertModelMissing($current->accessToken);
    $this->assertModelExists($other->accessToken);
    Auth::forgetGuards();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('logout all revokes the owners tokens and preserves another accounts tokens', function () {
    $user = User::factory()->create();
    $current = $user->createToken('current', ['mobile']);
    $user->createToken('second', ['mobile']);
    $other = User::factory()->create()->createToken('other', ['mobile']);

    $this->withToken($current->plainTextToken)->postJson('/api/v1/auth/logout-all')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
    $this->assertModelExists($other->accessToken);
});

test('profile rejects unexpected query parameters', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken)
        ->getJson('/api/v1/auth/me?user_id=999')->assertUnprocessable()->assertJsonValidationErrors('user_id');
});

test('login returns 429 after five attempts for normalized email and IP', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $email = $attempt % 2 === 0 ? ' PERSON@example.com ' : 'person@example.com';
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong', 'device_name' => 'Android'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'person@example.com', 'password' => 'wrong', 'device_name' => 'Android'])
        ->assertTooManyRequests()->assertHeader('Retry-After')->assertExactJson(['message' => 'Too many requests.']);
});

test('registration returns 429 after five requests from the same IP', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/register', [])->assertTooManyRequests();
});

test('registration accepts an optional international phone number', function (?string $phone) {
    $payload = [...mobileRegistrationPayload(), 'phone' => $phone];

    $this->postJson('/api/v1/auth/register', $payload)->assertCreated()->assertJsonPath('data.user.phone', $phone);

    expect(User::sole()->phone)->toBe($phone);
})->with([null, '+201234567890']);

test('mobile login returns 422 for missing or malformed credentials', function (array $payload, array $fields) {
    $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable()->assertJsonValidationErrors($fields);

    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'missing fields' => [[], ['email', 'password', 'device_name']],
    'array email' => [['email' => ['invalid'], 'password' => 'password', 'device_name' => 'Android'], ['email']],
    'array password' => [['email' => 'user@example.com', 'password' => ['invalid'], 'device_name' => 'Android'], ['password']],
]);
