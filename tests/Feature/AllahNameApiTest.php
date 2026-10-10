<?php

use App\Models\AllahName;
use App\Models\User;
use Database\Seeders\AllahNameSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

test('the offline seeder supplies ninety-nine sequential unique Arabic names with English meanings', function () {
    $this->seed(AllahNameSeeder::class);

    $names = AllahName::query()->orderBy('number')->get();
    expect($names->pluck('number')->all())->toBe(range(1, 99));
    expect($names->pluck('arabic_name')->unique())->toHaveCount(99);
    expect($names->pluck('language')->unique()->all())->toBe(['en']);
    foreach ($names as $name) {
        foreach (['arabic_name', 'transliteration', 'meaning'] as $field) {
            expect($name->{$field})->toBeString()->not->toBe('');
            expect(mb_check_encoding($name->{$field}, 'UTF-8'))->toBeTrue();
            expect(mb_strlen($name->{$field}))->toBeLessThanOrEqual(160);
            expect($name->{$field})->not->toContain('<', "\0");
        }
        expect(preg_match('/\p{Arabic}/u', $name->arabic_name))->toBe(1);
    }
    $this->assertDatabaseHas('allah_names', ['number' => 1, 'arabic_name' => 'الرَّحْمَنُ', 'transliteration' => 'Ar Rahmaan', 'meaning' => 'The Beneficent']);
    $this->assertDatabaseHas('allah_names', ['number' => 24, 'arabic_name' => 'الْمُعِزُّ', 'meaning' => 'The Bestower of Honour']);
    $this->assertDatabaseHas('allah_names', ['number' => 97, 'meaning' => 'The Inheritor']);
    $this->assertDatabaseHas('allah_names', ['number' => 99, 'arabic_name' => 'الصَّبُورُ', 'meaning' => 'The Patient One']);
    Http::assertNothingSent();
});

test('seeder reruns neither duplicate records nor overwrite existing editorial content', function () {
    $edited = AllahName::factory()->create(['number' => 1, 'language' => 'en', 'meaning' => 'Reviewed editorial meaning']);
    $otherLanguage = AllahName::factory()->create(['number' => 1, 'language' => 'ar']);

    $this->seed(AllahNameSeeder::class);
    $ids = AllahName::query()->orderBy('id')->pluck('id')->all();
    $this->seed(AllahNameSeeder::class);
    $this->assertDatabaseCount('allah_names', 100);
    expect(AllahName::query()->orderBy('id')->pluck('id')->all())->toBe($ids);
    expect($edited->fresh()->meaning)->toBe('Reviewed editorial meaning');
    $this->assertModelExists($otherLanguage);
    Http::assertNothingSent();
});

test('public Asma lists are paginated ordered and expose the meaning language', function () {
    $this->seed(AllahNameSeeder::class);
    AllahName::factory()->create(['number' => 1, 'language' => 'ar']);

    $this->getJson('/api/v1/asma-al-husna')->assertOk()->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.number', 1)->assertJsonPath('data.0.language', 'en')
        ->assertJsonPath('meta.total', 99)->assertJsonPath('meta.last_page', 5);
    $this->getJson('/api/v1/asma-al-husna?page=2&per_page=3&language=en')->assertOk()->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.number', 4)->assertJsonPath('data.2.number', 6)->assertJsonPath('meta.total', 99);
    $this->getJson('/api/v1/asma-al-husna?page=99')->assertOk()->assertJsonCount(0, 'data');
    Http::assertNothingSent();
});

test('Asma details use the name number rather than database IDs and preserve Arabic bytes', function () {
    AllahName::factory()->create(['id' => 300, 'number' => 1, 'language' => 'en', 'arabic_name' => 'الرَّحْمَنُ',
        'transliteration' => 'Ar Rahmaan', 'meaning' => 'The Beneficent']);

    $this->getJson('/api/v1/asma-al-husna/1?language=en')->assertOk()->assertExactJson(['data' => [
        'number' => 1, 'arabic_name' => 'الرَّحْمَنُ', 'transliteration' => 'Ar Rahmaan', 'meaning' => 'The Beneficent', 'language' => 'en',
    ]]);
    $this->json('HEAD', '/api/v1/asma-al-husna/1')->assertOk();
    Http::assertNothingSent();
});

test('invalid Asma query fields return 422 without provider requests', function (string $path, string $field) {
    $this->getJson('/api/v1/'.$path)->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([
    ['asma-al-husna?language=ar', 'language'], ['asma-al-husna?language=fr', 'language'],
    ['asma-al-husna?language=', 'language'], ['asma-al-husna?language[]=en', 'language'],
    ['asma-al-husna?page=0', 'page'], ['asma-al-husna?page=100', 'page'], ['asma-al-husna?page=1.5', 'page'],
    ['asma-al-husna?page[]=1', 'page'], ['asma-al-husna?per_page=0', 'per_page'], ['asma-al-husna?per_page=100', 'per_page'],
    ['asma-al-husna?sort=number', 'sort'], ['asma-al-husna/1?page=1', 'page'],
    ['asma-al-husna/1?language=en%27%20OR%201%3D1', 'language'],
]);

test('missing or invalid Asma numbers return safe 404', function (string $number) {
    $this->getJson('/api/v1/asma-al-husna/'.$number)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    Http::assertNothingSent();
})->with(['1', '0', '-1', '100', 'abc', '1.5', '01', '9999999999999999999']);

test('Asma API is read only', function () {
    $name = AllahName::factory()->create(['number' => 1, 'language' => 'en']);

    $this->postJson('/api/v1/asma-al-husna', ['meaning' => 'Overwrite'])->assertMethodNotAllowed();
    $this->patchJson('/api/v1/asma-al-husna/1', ['meaning' => 'Overwrite'])->assertMethodNotAllowed();
    $this->deleteJson('/api/v1/asma-al-husna/1')->assertMethodNotAllowed();
    expect($name->fresh()->meaning)->toBe('Test fixture — not verified Islamic content');
    Http::assertNothingSent();
});

test('Asma guest limits stop requests after sixty per minute across routes', function () {
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $this->getJson('/api/v1/asma-al-husna?language=invalid')->assertUnprocessable();
    }
    $this->getJson('/api/v1/asma-al-husna/1')->assertTooManyRequests()->assertHeader('Retry-After');
    Http::assertNothingSent();
});

test('Asma rate limits identify authenticated users independently on a shared IP', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->withToken($first->createToken('mobile', ['mobile'])->plainTextToken);
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $this->getJson('/api/v1/asma-al-husna?language=invalid')->assertUnprocessable();
    }
    $this->getJson('/api/v1/asma-al-husna?language=invalid')->assertTooManyRequests();
    Auth::forgetGuards();
    $this->withToken($second->createToken('mobile', ['mobile'])->plainTextToken);
    $this->getJson('/api/v1/asma-al-husna?language=invalid')->assertUnprocessable();
    Http::assertNothingSent();
});
