<?php

use App\AccountStatus;
use App\Models\QuranPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

/** Authenticate through an actual mobile token for preference tests. */
function quranPreferenceActor(): User
{
    $user = User::factory()->create();
    test()->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    return $user;
}

test('Quran preference reads return defaults without writing or contacting a provider', function () {
    quranPreferenceActor();
    QuranPreference::factory()->create(['translation_edition' => 'en.sahih', 'reciter_identifier' => 'ar.alafasy']);

    $this->getJson('/api/v1/quran/preferences')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['data' => ['translation_edition' => null, 'reciter_identifier' => null]]);
    $this->json('HEAD', '/api/v1/quran/preferences')->assertOk();
    $this->assertDatabaseCount('quran_preferences', 1);
    Http::assertNothingSent();
});

test('Quran preferences support owned partial updates and null resets without duplicate rows', function () {
    $user = quranPreferenceActor();
    $foreign = QuranPreference::factory()->create(['translation_edition' => null, 'reciter_identifier' => null]);

    $this->patchJson('/api/v1/quran/preferences', ['translation_edition' => 'en.sahih'])->assertOk()
        ->assertJsonPath('data.reciter_identifier', null);
    $this->patchJson('/api/v1/quran/preferences', ['reciter_identifier' => 'ar.alafasy'])->assertOk()
        ->assertExactJson(['data' => ['translation_edition' => 'en.sahih', 'reciter_identifier' => 'ar.alafasy']]);
    $this->getJson('/api/v1/quran/preferences')->assertOk()->assertJsonPath('data.translation_edition', 'en.sahih');
    $this->assertDatabaseHas('quran_preferences', ['user_id' => $user->id, 'translation_edition' => 'en.sahih', 'reciter_identifier' => 'ar.alafasy']);
    $this->patchJson('/api/v1/quran/preferences', ['translation_edition' => null, 'reciter_identifier' => null])->assertOk()
        ->assertExactJson(['data' => ['translation_edition' => null, 'reciter_identifier' => null]]);
    $this->assertDatabaseCount('quran_preferences', 2);
    $this->assertDatabaseHas('quran_preferences', ['user_id' => $user->id, 'translation_edition' => null, 'reciter_identifier' => null]);
    expect($foreign->fresh()->translation_edition)->toBeNull();
    expect($foreign->fresh()->reciter_identifier)->toBeNull();
    Http::assertNothingSent();
});

test('invalid Quran preferences return 422 without modifying saved selections', function (array $input, string $field) {
    $user = quranPreferenceActor();
    $preferences = QuranPreference::factory()->for($user)->create(['translation_edition' => 'en.sahih', 'reciter_identifier' => 'ar.alafasy']);

    $this->patchJson('/api/v1/quran/preferences', $input)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($preferences->fresh()->only(['translation_edition', 'reciter_identifier']))
        ->toBe(['translation_edition' => 'en.sahih', 'reciter_identifier' => 'ar.alafasy']);
    $this->assertDatabaseCount('quran_preferences', 1);
    Http::assertNothingSent();
})->with([
    'unknown translation' => [['translation_edition' => 'en.other'], 'translation_edition'],
    'Arabic text is not translation' => [['translation_edition' => 'quran-uthmani'], 'translation_edition'],
    'translation array' => [['translation_edition' => ['en.sahih']], 'translation_edition'],
    'translation length' => [['translation_edition' => str_repeat('a', 101)], 'translation_edition'],
    'unknown reciter' => [['reciter_identifier' => 'ar.other'], 'reciter_identifier'],
    'reciter array' => [['reciter_identifier' => ['ar.alafasy']], 'reciter_identifier'],
    'reciter length' => [['reciter_identifier' => str_repeat('a', 101)], 'reciter_identifier'],
    'owner injection' => [['user_id' => 2], 'user_id'],
    'URL injection' => [['reciter_identifier' => 'https://attacker.example'], 'reciter_identifier'],
    'empty patch' => [[], 'preferences'],
]);

test('Quran preference reads reject query fields', function () {
    quranPreferenceActor();

    $this->getJson('/api/v1/quran/preferences?user_id=2')->assertUnprocessable()->assertJsonValidationErrors('user_id');
    Http::assertNothingSent();
});

test('Quran preference endpoints require mobile bearer authentication', function (string $method) {
    $this->{$method}('/api/v1/quran/preferences', [])->assertUnauthorized();
    Http::assertNothingSent();
})->with(['getJson', 'patchJson']);

test('Quran preferences reject browser sessions', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->patchJson('/api/v1/quran/preferences', ['translation_edition' => 'en.sahih'])->assertUnauthorized();
    $this->assertDatabaseCount('quran_preferences', 0);
    Http::assertNothingSent();
});

test('suspended accounts and wrong token abilities cannot update preferences', function (string $kind) {
    $user = User::factory()->create(['account_status' => $kind === 'suspended' ? AccountStatus::Suspended : AccountStatus::Active]);
    $this->withToken($user->createToken('test', $kind === 'suspended' ? ['mobile'] : ['read'])->plainTextToken);

    $this->patchJson('/api/v1/quran/preferences', ['translation_edition' => 'en.sahih'])->assertForbidden();
    $this->assertDatabaseCount('quran_preferences', 0);
    Http::assertNothingSent();
})->with(['suspended', 'wrong ability']);

test('Quran preference writes share the thirty-per-minute tracking limit', function () {
    quranPreferenceActor();

    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->patchJson('/api/v1/quran/preferences', ['translation_edition' => 'en.sahih'])->assertOk();
    }
    $this->patchJson('/api/v1/quran/preferences', ['translation_edition' => null])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->getJson('/api/v1/quran/preferences')->assertOk()->assertJsonPath('data.translation_edition', 'en.sahih');
    $this->assertDatabaseCount('quran_preferences', 1);
    Http::assertNothingSent();
});
