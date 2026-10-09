<?php

use App\AccountStatus;
use App\Models\PrayerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('reading absent prayer settings returns defaults without writing', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    $this->getJson('/api/v1/prayer-settings')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['data' => ['calculation_method' => null, 'school' => 0, 'high_latitude_method' => null, 'timezone' => null]]);
    $this->assertDatabaseCount('prayer_settings', 0);
});

test('partial updates create one owned row and preserve unspecified settings', function () {
    $user = User::factory()->create();
    $other = PrayerSetting::factory()->create(['school' => 1, 'timezone' => 'Asia/Riyadh']);
    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    $this->patchJson('/api/v1/prayer-settings', ['calculation_method' => 5, 'timezone' => 'Africa/Cairo'])
        ->assertOk()->assertJsonPath('data.school', 0)->assertJsonPath('data.calculation_method', 5);
    $this->patchJson('/api/v1/prayer-settings', ['school' => 1, 'high_latitude_method' => 2])
        ->assertOk()->assertExactJson(['data' => ['calculation_method' => 5, 'school' => 1, 'high_latitude_method' => 2, 'timezone' => 'Africa/Cairo']]);
    $this->getJson('/api/v1/prayer-settings')->assertOk()->assertJsonPath('data.timezone', 'Africa/Cairo');
    $this->assertDatabaseCount('prayer_settings', 2);
    $this->assertDatabaseHas('prayer_settings', ['user_id' => $user->id, 'calculation_method' => 5, 'school' => 1, 'high_latitude_method' => 2, 'timezone' => 'Africa/Cairo', 'notifications_enabled' => false]);
    expect($other->fresh()->timezone)->toBe('Asia/Riyadh');
});

test('explicit null resets automatic preferences without resetting school', function () {
    $settings = PrayerSetting::factory()->create(['calculation_method' => 5, 'school' => 1, 'high_latitude_method' => 2, 'timezone' => 'Africa/Cairo']);
    $this->withToken($settings->user->createToken('mobile', ['mobile'])->plainTextToken);

    $this->patchJson('/api/v1/prayer-settings', ['calculation_method' => null, 'high_latitude_method' => null, 'timezone' => null])
        ->assertOk()->assertExactJson(['data' => ['calculation_method' => null, 'school' => 1, 'high_latitude_method' => null, 'timezone' => null]]);
    $this->assertDatabaseHas('prayer_settings', ['id' => $settings->id, 'calculation_method' => null, 'school' => 1, 'high_latitude_method' => null, 'timezone' => null]);
});

test('invalid settings return 422 and leave stored preferences unchanged', function (array $input, string $field) {
    $settings = PrayerSetting::factory()->create(['calculation_method' => 5, 'timezone' => 'Africa/Cairo']);
    $this->withToken($settings->user->createToken('mobile', ['mobile'])->plainTextToken);

    $this->patchJson('/api/v1/prayer-settings', $input)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($settings->fresh()->only(['calculation_method', 'school', 'high_latitude_method', 'timezone']))
        ->toBe(['calculation_method' => 5, 'school' => 0, 'high_latitude_method' => null, 'timezone' => 'Africa/Cairo']);
    $this->assertDatabaseCount('prayer_settings', 1);
})->with([
    'undefined method' => [['calculation_method' => 6], 'calculation_method'],
    'custom method' => [['calculation_method' => 99], 'calculation_method'],
    'array method' => [['calculation_method' => [5]], 'calculation_method'],
    'fractional method' => [['calculation_method' => 5.5], 'calculation_method'],
    'school range' => [['school' => 2], 'school'],
    'null school' => [['school' => null], 'school'],
    'negative latitude method' => [['high_latitude_method' => 0], 'high_latitude_method'],
    'latitude method range' => [['high_latitude_method' => 4], 'high_latitude_method'],
    'unknown timezone' => [['timezone' => 'Invalid/Zone'], 'timezone'],
    'long timezone' => [['timezone' => str_repeat('a', 65)], 'timezone'],
    'array timezone' => [['timezone' => ['Africa/Cairo']], 'timezone'],
    'owner injection' => [['user_id' => 1, 'school' => 1], 'user_id'],
    'record injection' => [['id' => 1, 'school' => 1], 'id'],
    'coordinates unsupported' => [['latitude' => 30, 'school' => 1], 'latitude'],
    'reminders unsupported' => [['notifications_enabled' => true], 'notifications_enabled'],
    'empty update' => [[], 'settings'],
]);

test('settings GET rejects owner selection and returns 422', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    $this->getJson('/api/v1/prayer-settings?user_id=123')->assertUnprocessable()->assertJsonValidationErrors('user_id');
    $this->assertDatabaseCount('prayer_settings', 0);
});

test('settings require mobile bearer authentication and return 401', function (string $method) {
    $this->{$method}('/api/v1/prayer-settings', [])->assertUnauthorized();
    $this->assertDatabaseCount('prayer_settings', 0);
})->with(['getJson', 'patchJson']);

test('browser sessions cannot access mobile settings and return 401', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->patchJson('/api/v1/prayer-settings', ['school' => 1])->assertUnauthorized();
    $this->assertDatabaseCount('prayer_settings', 0);
});

test('suspended accounts and tokens without mobile ability receive 403', function (string $kind) {
    $user = User::factory()->create(['account_status' => $kind === 'suspended' ? AccountStatus::Suspended : AccountStatus::Active]);
    $token = $user->createToken('test', $kind === 'suspended' ? ['mobile'] : ['read']);
    $this->withToken($token->plainTextToken);

    $this->patchJson('/api/v1/prayer-settings', ['school' => 1])->assertForbidden();
    $this->assertDatabaseCount('prayer_settings', 0);
})->with(['suspended', 'wrong ability']);

test('settings share the authenticated API limit and return 429 after 120 requests', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    for ($attempt = 0; $attempt < 120; $attempt++) {
        $this->getJson('/api/v1/prayer-settings')->assertOk();
    }
    $this->patchJson('/api/v1/prayer-settings', ['school' => 1])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->assertDatabaseCount('prayer_settings', 0);
});

test('settings writes have a separate per-user limit of thirty per minute', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->patchJson('/api/v1/prayer-settings', ['school' => 1])->assertOk();
    }
    $this->patchJson('/api/v1/prayer-settings', ['school' => 0])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->getJson('/api/v1/prayer-settings')->assertOk()->assertJsonPath('data.school', 1);
    $this->assertDatabaseCount('prayer_settings', 1);
});
