<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

/** Synthetic metadata fixtures, not production religious reference data. */
function quranMetadataFixture(): array
{
    return ['code' => 200, 'data' => array_map(fn (int $number): array => [
        'number' => $number, 'name' => 'اسم تجريبي', 'englishName' => 'Test surah',
        'englishNameTranslation' => 'Test meaning', 'numberOfAyahs' => 7,
        'revelationType' => 'Meccan', 'private_marker' => 'provider-only-marker',
    ], range(1, 114))];
}

/** Synthetic text deliberately differs from scripture and preserves meaningful formatting. */
function quranAyahFixture(string $edition = 'quran-uthmani'): array
{
    return ['code' => 200, 'data' => [
        'number' => 1, 'numberOfAyahs' => 7, 'edition' => ['identifier' => $edition, 'format' => 'text'],
        'ayahs' => array_map(fn (int $number): array => [
            'number' => $number, 'numberInSurah' => $number, 'text' => "  نَصٌّ تَجْرِيبِيٌّ\n  ",
            'private_marker' => 'provider-only-marker',
        ], range(1, 7)),
    ]];
}

test('public Quran metadata is mapped paginated and cached across pages and details', function () {
    Http::fake(['api.alquran.cloud/v1/surah' => Http::response(quranMetadataFixture())]);

    $this->getJson('/api/v1/quran/surahs?per_page=2&page=2')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.number', 3)->assertJsonPath('data.0.revelation_type', 'meccan')
        ->assertJsonPath('meta.total', 114)->assertJsonPath('meta.last_page', 57)->assertDontSee('provider-only-marker');
    $this->getJson('/api/v1/quran/surahs/114')->assertOk()->assertExactJson(['data' => [
        'number' => 114, 'name' => 'اسم تجريبي', 'english_name' => 'Test surah',
        'english_name_translation' => 'Test meaning', 'ayah_count' => 7, 'revelation_type' => 'meccan',
    ]]);
    Http::assertSentCount(1);
});

test('Quran ayahs preserve text bytes and paginate without caching raw provider fields', function () {
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/quran-uthmani' => Http::response(quranAyahFixture()),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/ayahs?per_page=3&page=2')->assertOk()
        ->assertJsonCount(3, 'data')->assertJsonPath('data.0.ayah_number', 4)
        ->assertJsonPath('data.0.global_number', 4)->assertJsonPath('data.0.text', "  نَصٌّ تَجْرِيبِيٌّ\n  ")
        ->assertJsonPath('edition', 'quran-uthmani')->assertJsonPath('surah.number', 1)
        ->assertJsonPath('meta.total', 7)->assertDontSee('provider-only-marker');
    $this->getJson('/api/v1/quran/surahs/1/ayahs?per_page=3&page=3')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/quran/surahs/1/ayahs?page=286')->assertOk()->assertJsonCount(0, 'data');
    Http::assertSentCount(2);
    $this->assertDatabaseCount('quran_bookmarks', 0);
    $this->assertDatabaseCount('quran_preferences', 0);
});

test('translation editions have independent caches and a configurable expiration', function () {
    config(['services.quran.cache_ttl.ayahs' => 60]);
    $this->freezeTime();
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/quran-uthmani' => Http::response(quranAyahFixture()),
        'api.alquran.cloud/v1/surah/1/en.sahih' => Http::response(quranAyahFixture('en.sahih')),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/ayahs')->assertOk();
    $this->getJson('/api/v1/quran/surahs/1/ayahs?edition=en.sahih')->assertOk()->assertJsonPath('edition', 'en.sahih');
    $this->travel(61)->seconds();
    $this->getJson('/api/v1/quran/surahs/1/ayahs?edition=en.sahih')->assertOk();
    Http::assertSentCount(4);
});

test('invalid Quran input returns 422 before contacting the provider', function (string $path, string $field) {
    $this->getJson('/api/v1/quran/'.$path)->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([
    'surah upper bound' => ['surahs/115', 'surah'],
    'ayah surah upper bound' => ['surahs/999/ayahs', 'surah'],
    'route override' => ['surahs/1/ayahs?surah=2', 'surah'],
    'page zero' => ['surahs?page=0', 'page'],
    'page upper bound' => ['surahs?page=287', 'page'],
    'page array' => ['surahs?page[]=1', 'page'],
    'fractional page' => ['surahs?page=1.5', 'page'],
    'per page upper bound' => ['surahs?per_page=101', 'per_page'],
    'per page lower bound' => ['surahs?per_page=0', 'per_page'],
    'empty edition' => ['surahs/1/ayahs?edition=', 'edition'],
    'audio edition' => ['surahs/1/ayahs?edition=ar.alafasy', 'edition'],
    'URL edition' => ['surahs/1/ayahs?edition=https://attacker.example', 'edition'],
    'array edition' => ['surahs/1/ayahs?edition[]=en.sahih', 'edition'],
    'unexpected query' => ['surahs?base_url=https://attacker.example', 'base_url'],
    'metadata details query' => ['surahs/1?page=1', 'page'],
]);

test('malformed Quran route identifiers return 404 without provider requests', function (string $number) {
    $this->getJson('/api/v1/quran/surahs/'.$number)->assertNotFound();
    Http::assertNothingSent();
})->with(['0', '-1', '1.5', 'abc', '9999']);

test('provider errors return safe 503 and bounded retries without exposing response details', function (string $kind, int $attempts) {
    Sleep::fake();
    Log::spy();
    $response = match ($kind) {
        'connection' => Http::failedConnection('private-provider-secret'),
        'server' => Http::response('private-provider-secret', 500),
        'client' => Http::response('private-provider-secret', 400),
        'limit' => Http::response('private-provider-secret', 429),
        'redirect' => Http::response('', 302, ['Location' => 'https://attacker.example']),
        'json' => Http::response('not-json-private-provider-secret'),
        'code' => Http::response(['code' => 500, 'data' => []]),
        'data' => Http::response(['code' => 200, 'data' => 'private-provider-secret']),
    };
    Http::fake(['api.alquran.cloud/v1/surah' => $response]);

    $this->getJson('/api/v1/quran/surahs')->assertServiceUnavailable()->assertExactJson(['message' => 'Server error.']);
    Http::assertSentCount($attempts);
    Log::shouldHaveReceived('warning')->with('Quran provider request failed.', Mockery::on(fn (array $context): bool => array_keys($context) === ['reason', 'status']))->once();
})->with([
    'connection' => ['connection', 2], 'server' => ['server', 2], 'client' => ['client', 1],
    'rate limit' => ['limit', 1], 'redirect' => ['redirect', 1], 'invalid JSON' => ['json', 1],
    'wrong code' => ['code', 1], 'wrong data' => ['data', 1],
]);

test('malformed Quran metadata is rejected and never cached', function (string $kind) {
    $fixture = quranMetadataFixture();
    match ($kind) {
        'count' => array_pop($fixture['data']),
        'number' => $fixture['data'][0]['number'] = 2,
        'ayah count' => $fixture['data'][0]['numberOfAyahs'] = 287,
        'label' => $fixture['data'][0]['name'] = '<script>marker</script>',
        'type' => $fixture['data'][0]['revelationType'] = 'unknown',
    };
    Http::fake(['api.alquran.cloud/v1/surah' => Http::response($fixture)]);

    $this->getJson('/api/v1/quran/surahs')->assertServiceUnavailable();
    $this->getJson('/api/v1/quran/surahs')->assertServiceUnavailable();
    Http::assertSentCount(2);
})->with(['count', 'number', 'ayah count', 'label', 'type']);

test('malformed Quran ayahs are rejected and never cached', function (string $kind) {
    $fixture = quranAyahFixture();
    match ($kind) {
        'surah' => $fixture['data']['number'] = 2,
        'count' => array_pop($fixture['data']['ayahs']),
        'edition' => $fixture['data']['edition']['identifier'] = 'en.other',
        'format' => $fixture['data']['edition']['format'] = 'audio',
        'number' => $fixture['data']['ayahs'][0]['numberInSurah'] = 2,
        'global number' => $fixture['data']['ayahs'][0]['number'] = 6237,
        'sequence' => $fixture['data']['ayahs'][1]['number'] = 4,
        'text' => $fixture['data']['ayahs'][0]['text'] = '<script>marker</script>',
        'null text' => $fixture['data']['ayahs'][0]['text'] = null,
        'long text' => $fixture['data']['ayahs'][0]['text'] = str_repeat('a', 20001),
    };
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/quran-uthmani' => Http::response($fixture),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/ayahs')->assertServiceUnavailable();
    $this->getJson('/api/v1/quran/surahs/1/ayahs')->assertServiceUnavailable();
    Http::assertSentCount(3);
})->with(['surah', 'count', 'edition', 'format', 'number', 'global number', 'sequence', 'text', 'null text', 'long text']);

test('invalid Quran provider configuration returns 503 before sending HTTP', function (string $url) {
    config(['services.quran.base_url' => $url]);

    $this->getJson('/api/v1/quran/surahs')->assertServiceUnavailable();
    Http::assertNothingSent();
})->with(['http://api.example/v1', 'https://user:password@api.example/v1', 'https://api.example/v1?key=secret', 'https://api.example/v1#fragment']);

test('Quran rate limits apply across endpoints and stop guests after ninety requests', function () {
    for ($attempt = 0; $attempt < 90; $attempt++) {
        $this->getJson('/api/v1/quran/surahs?page=0')->assertUnprocessable();
    }
    $this->getJson('/api/v1/quran/surahs/1/ayahs')->assertTooManyRequests()->assertHeader('Retry-After');
    Http::assertNothingSent();
});

test('Quran rate limits identify authenticated users independently on a shared IP', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->withToken($first->createToken('mobile', ['mobile'])->plainTextToken);
    for ($attempt = 0; $attempt < 90; $attempt++) {
        $this->getJson('/api/v1/quran/surahs?page=0')->assertUnprocessable();
    }
    $this->getJson('/api/v1/quran/surahs?page=0')->assertTooManyRequests();
    Auth::forgetGuards();
    $this->withToken($second->createToken('mobile', ['mobile'])->plainTextToken);
    $this->getJson('/api/v1/quran/surahs?page=0')->assertUnprocessable();
    Http::assertNothingSent();
});

test('Quran global ayah numbers must start at the correct surah offset', function () {
    $fixture = quranAyahFixture();
    foreach ($fixture['data']['ayahs'] as &$ayah) {
        $ayah['number'] += 1;
    }
    unset($ayah);
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/quran-uthmani' => Http::response($fixture),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/ayahs')->assertServiceUnavailable();
    Http::assertSentCount(2);
});

test('a transient Quran provider failure retries once and returns mapped content', function () {
    Sleep::fake();
    Http::fake(['api.alquran.cloud/v1/surah' => Http::sequence()->push([], 503)->push(quranMetadataFixture())]);

    $this->getJson('/api/v1/quran/surahs')->assertOk()->assertJsonCount(20, 'data');
    Http::assertSentCount(2);
});
