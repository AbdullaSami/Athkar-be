<?php

use App\AccountStatus;
use App\Models\User;
use App\Notifications\ResetAccountPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['auth.password_reset_url' => 'https://app.example/reset-password', 'auth.password_reset_mailer' => 'array']);
});

test('forgot password sends an eligible user a hashed expiring proof without returning it', function () {
    $user = User::factory()->create(['email' => 'person@example.com']);
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => ' PERSON@example.com ']);

    $response->assertAccepted()->assertExactJson(['message' => 'If the account is eligible, password reset instructions will be sent.']);
    Notification::assertSentTo($user, ResetAccountPassword::class, function (ResetAccountPassword $notification) use ($user): bool {
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        expect($stored)->not->toBe($notification->token);
        expect(Hash::check($notification->token, $stored))->toBeTrue();
        $mail = $notification->toMail($user);
        expect($mail->mailer)->toBe('array');
        expect($mail->actionUrl)->toStartWith('https://app.example/reset-password?');
        expect($mail->actionUrl)->toContain('email=person%40example.com');

        return true;
    });
});

test('unknown, suspended and Google-only accounts receive the same accepted response without notifications', function (string $kind) {
    $email = 'unknown@example.com';
    if ($kind === 'google') {
        $email = User::factory()->google()->create()->email;
    } elseif ($kind === 'suspended') {
        $email = User::factory()->create(['account_status' => AccountStatus::Suspended])->email;
    }
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])->assertAccepted()
        ->assertExactJson(['message' => 'If the account is eligible, password reset instructions will be sent.']);

    Notification::assertNothingSent();
    $this->assertDatabaseCount('password_reset_tokens', 0);
})->with(['unknown', 'suspended', 'google']);

test('repeated recovery requests do not send multiple links during the broker cooldown', function () {
    $user = User::factory()->create();
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertAccepted();

    Notification::assertSentToTimes($user, ResetAccountPassword::class, 1);
});

test('reset changes the password once and revokes only that users tokens and database sessions', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    $oldRememberToken = $user->remember_token;
    $user->createToken('mobile', ['mobile']);
    $otherToken = $other->createToken('other', ['mobile']);
    DB::table('sessions')->insert([
        ['id' => 'user-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'other-session', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
    ]);
    $token = Password::broker()->createToken($user);
    $payload = ['email' => $user->email, 'token' => $token, 'password' => '  new secure password  ', 'password_confirmation' => '  new secure password  '];

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk()->assertJsonMissingPath('token');

    expect(Hash::check($payload['password'], $user->fresh()->password))->toBeTrue();
    expect(Hash::check('password', $user->fresh()->password))->toBeFalse();
    expect($user->fresh()->remember_token)->not->toBe($oldRememberToken);
    expect($user->tokens()->count())->toBe(0);
    $this->assertModelExists($otherToken->accessToken);
    $this->assertDatabaseMissing('sessions', ['id' => 'user-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    $this->assertDatabaseCount('password_reset_tokens', 0);
    $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
    $this->assertGuest('web');
});

test('expired, wrong and cross-account proofs cannot change passwords or revoke tokens', function (string $kind) {
    $this->freezeTime();
    $user = User::factory()->create();
    $original = $user->password;
    $mobile = $user->createToken('mobile', ['mobile']);
    $token = Password::broker()->createToken($user);
    if ($kind === 'expired') {
        $this->travel(61)->minutes();
    } elseif ($kind === 'wrong') {
        $token = str_repeat('a', 64);
    } else {
        $token = Password::broker()->createToken(User::factory()->create());
    }

    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token,
        'password' => 'new secure password', 'password_confirmation' => 'new secure password'])
        ->assertUnprocessable()->assertJsonPath('errors.token.0', 'This password reset link is invalid or expired.');

    expect($user->fresh()->password)->toBe($original);
    $this->assertModelExists($mobile->accessToken);
})->with(['expired', 'wrong', 'cross-account']);

test('a proof cannot enable local passwords on Google-only or suspended accounts', function (string $kind) {
    $user = $kind === 'google' ? User::factory()->google()->create() : User::factory()->create(['account_status' => AccountStatus::Suspended]);
    $original = $user->password;
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token,
        'password' => 'new secure password', 'password_confirmation' => 'new secure password'])->assertUnprocessable();

    expect($user->fresh()->password)->toBe($original);
})->with(['google', 'suspended']);

test('reset validation rejects unsupported input and unsafe password bytes', function (string $field, mixed $value, string $error) {
    $payload = ['email' => 'person@example.com', 'token' => str_repeat('a', 64),
        'password' => 'new secure password', 'password_confirmation' => 'new secure password'];
    $payload[$field] = $value;
    if ($field === 'password') {
        $payload['password_confirmation'] = $value;
    }

    $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'invalid email' => ['email', 'not-email', 'email'],
    'token whitespace' => ['token', ' '.str_repeat('a', 64).' ', 'token'],
    'token array' => ['token', [], 'token'],
    'short password' => ['password', 'short', 'password'],
    'bcrypt overflow' => ['password', str_repeat('ع', 37), 'password'],
    'null byte' => ['password', "invalid\0password", 'password'],
    'confirmation mismatch' => ['password_confirmation', 'mismatch password', 'password'],
    'owner injection' => ['user_id', 123, 'user_id'],
]);

test('unsafe or missing reset destinations and logging mailers fail before looking up accounts', function (mixed $url, string $mailer) {
    config(['auth.password_reset_url' => $url, 'auth.password_reset_mailer' => $mailer]);
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.com'])->assertServiceUnavailable();

    Notification::assertNothingSent();
})->with([
    'missing URL' => [null, 'array'], 'insecure URL' => ['http://app.example/reset', 'array'],
    'URL credentials' => ['https://user@app.example/reset', 'array'],
    'URL query' => ['https://app.example/reset?redirect=other', 'array'],
    'URL fragment' => ['https://app.example/reset#fragment', 'array'],
    'logging mailer' => ['https://app.example/reset', 'log'],
    'logging failover' => ['https://app.example/reset', 'failover'],
]);

test('delivery failures retain the generic response and log no credential details', function () {
    User::factory()->create(['email' => 'person@example.com']);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('private-reset-token'));
    Log::shouldReceive('warning')->once()->with('Password recovery delivery failed.', ['exception_type' => RuntimeException::class]);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'person@example.com'])->assertAccepted()
        ->assertExactJson(['message' => 'If the account is eligible, password reset instructions will be sent.']);
});

test('recovery endpoints enforce their distinct public rate limits', function (string $endpoint, int $limit) {
    Notification::fake();
    for ($attempt = 0; $attempt < $limit; $attempt++) {
        $response = $this->postJson('/api/v1/auth/'.$endpoint, ['email' => ' PERSON@example.com ']);
        $endpoint === 'forgot-password' ? $response->assertAccepted() : $response->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/'.$endpoint, ['email' => 'person@example.com'])->assertTooManyRequests()->assertHeader('Retry-After');
})->with([['forgot-password', 3], ['reset-password', 5]]);

test('password reset invalidates an existing dashboard session even with a non-database session driver', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('access-dashboard', 'web');
    $role = Role::findOrCreate('dashboard administrator', 'web');
    $role->givePermissionTo($permission);
    $user->assignRole($role);
    $response = $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertOk();
    $cookie = $response->getCookie(config('session.cookie'))->getValue();
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', ['email' => $user->email, 'token' => $token,
        'password' => 'new secure password', 'password_confirmation' => 'new secure password'])->assertOk();

    Auth::forgetGuards();
    $this->withCookie(config('session.cookie'), $cookie)->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
});

test('forgot password rejects malformed emails and unsupported fields', function (array $payload, string $field) {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    Notification::assertNothingSent();
})->with([
    'missing email' => [[], 'email'],
    'email array' => [['email' => ['person@example.com']], 'email'],
    'oversized email' => [['email' => str_repeat('a', 255).'@example.com'], 'email'],
    'redirect injection' => [['email' => 'person@example.com', 'redirect_url' => 'https://attacker.example'], 'redirect_url'],
]);
