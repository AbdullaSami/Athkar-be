<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

/** Synthetic audio metadata, not a religious reference dataset. */
function quranAudioMetadataFixture(): array
{
    return ['code' => 200, 'data' => array_map(fn (int $number): array => [
        'number' => $number, 'name' => 'اختبار', 'englishName' => 'Test', 'englishNameTranslation' => 'Test',
        'numberOfAyahs' => 7, 'revelationType' => 'Meccan',
    ], range(1, 114))];
}

/** Synthetic provider fixture; no actual audio is fetched in tests. */
function quranAudioFixture(): array
{
    return ['code' => 200, 'data' => ['number' => 1, 'numberOfAyahs' => 7,
        'edition' => ['identifier' => 'ar.alafasy', 'format' => 'audio'],
        'ayahs' => array_map(fn (int $number): array => [
            'number' => $number, 'numberInSurah' => $number,
            'audio' => 'https://cdn.islamic.network/quran/audio/128/ar.alafasy/'.$number.'.mp3',
            'audioSecondary' => ['https://untrusted.example/file.mp3'], 'text' => 'Provider text is omitted',
        ], range(1, 7)),
    ]];
}

test('public Quran audio returns paginated validated URLs and caches mapped data', function () {
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranAudioMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/ar.alafasy' => Http::response(quranAudioFixture()),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/audio?per_page=2&page=2')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.ayah_number', 3)->assertJsonPath('data.0.global_number', 3)
        ->assertJsonPath('data.0.audio_url', 'https://cdn.islamic.network/quran/audio/128/ar.alafasy/3.mp3')
        ->assertJsonPath('reciter', 'ar.alafasy')->assertJsonPath('meta.total', 7)
        ->assertDontSee('audioSecondary')->assertDontSee('Provider text')->assertDontSee('untrusted.example');
    $this->getJson('/api/v1/quran/surahs/1/audio?reciter=ar.alafasy&page=4&per_page=2')->assertOk()->assertJsonCount(1, 'data');
    Http::assertSentCount(2);
});

test('Quran audio cache expires according to configuration', function () {
    config(['services.quran.cache_ttl.audio' => 60]);
    $this->freezeTime();
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranAudioMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/ar.alafasy' => Http::response(quranAudioFixture()),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/audio')->assertOk();
    $this->travel(61)->seconds();
    $this->getJson('/api/v1/quran/surahs/1/audio')->assertOk();
    Http::assertSentCount(3);
});

test('invalid audio query or route input returns 422 before provider requests', function (string $path, string $field) {
    $this->getJson('/api/v1/quran/'.$path)->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([
    ['surahs/115/audio', 'surah'], ['surahs/1/audio?reciter=en.sahih', 'reciter'],
    ['surahs/1/audio?reciter[]=ar.alafasy', 'reciter'], ['surahs/1/audio?reciter=https://attacker.example', 'reciter'],
    ['surahs/1/audio?edition=en.sahih', 'edition'], ['surahs/1/audio?page=0', 'page'],
    ['surahs/1/audio?per_page=101', 'per_page'], ['surahs/1/audio?base_url=https://attacker.example', 'base_url'],
]);

test('untrusted audio URLs return 503 and are never cached', function (mixed $url) {
    $fixture = quranAudioFixture();
    $fixture['data']['ayahs'][0]['audio'] = $url;
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranAudioMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/ar.alafasy' => Http::response($fixture),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/audio')->assertServiceUnavailable()->assertExactJson(['message' => 'Server error.']);
    $this->getJson('/api/v1/quran/surahs/1/audio')->assertServiceUnavailable();
    Http::assertSentCount(3);
})->with([
    'missing' => [null], 'array' => [['url']],
    'HTTP' => ['http://cdn.islamic.network/quran/audio/128/ar.alafasy/1.mp3'],
    'host' => ['https://attacker.example/quran/audio/128/ar.alafasy/1.mp3'],
    'host suffix' => ['https://cdn.islamic.network.attacker.example/quran/audio/128/ar.alafasy/1.mp3'],
    'credentials' => ['https://secret@cdn.islamic.network/quran/audio/128/ar.alafasy/1.mp3'],
    'port' => ['https://cdn.islamic.network:444/quran/audio/128/ar.alafasy/1.mp3'],
    'query' => ['https://cdn.islamic.network/quran/audio/128/ar.alafasy/1.mp3?secret=x'],
    'fragment' => ['https://cdn.islamic.network/quran/audio/128/ar.alafasy/1.mp3#x'],
    'wrong ayah' => ['https://cdn.islamic.network/quran/audio/128/ar.alafasy/2.mp3'],
    'wrong reciter' => ['https://cdn.islamic.network/quran/audio/128/ar.other/1.mp3'],
    'traversal' => ['https://cdn.islamic.network/quran/audio/128/ar.alafasy/../1.mp3'],
    'HTML' => ['<script>alert(1)</script>'],
]);

test('malformed audio metadata is rejected before caching', function (string $kind) {
    $fixture = quranAudioFixture();
    match ($kind) {
        'surah' => $fixture['data']['number'] = 2,
        'count' => array_pop($fixture['data']['ayahs']),
        'edition' => $fixture['data']['edition']['identifier'] = 'ar.other',
        'format' => $fixture['data']['edition']['format'] = 'text',
        'ayah' => $fixture['data']['ayahs'][0]['numberInSurah'] = 2,
        'global number' => $fixture['data']['ayahs'][0]['number'] = 2,
    };
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranAudioMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/ar.alafasy' => Http::response($fixture),
    ]);

    $this->getJson('/api/v1/quran/surahs/1/audio')->assertServiceUnavailable();
    Http::assertSentCount(2);
})->with(['surah', 'count', 'edition', 'format', 'ayah', 'global number']);

test('audio provider failures return safe 503 with controlled retries', function (string $kind, int $attempts) {
    Sleep::fake();
    $failure = match ($kind) {
        'connection' => Http::failedConnection('private-provider-marker'),
        'server' => Http::response('private-provider-marker', 500),
        'client' => Http::response('private-provider-marker', 400),
        'redirect' => Http::response('', 302, ['Location' => 'https://attacker.example']),
        'JSON' => Http::response('private-provider-marker'),
    };
    Http::fake([
        'api.alquran.cloud/v1/surah' => Http::response(quranAudioMetadataFixture()),
        'api.alquran.cloud/v1/surah/1/ar.alafasy' => $failure,
    ]);

    $this->getJson('/api/v1/quran/surahs/1/audio')->assertServiceUnavailable()->assertExactJson(['message' => 'Server error.']);
    Http::assertSentCount($attempts + 1);
})->with([['connection', 2], ['server', 2], ['client', 1], ['redirect', 1], ['JSON', 1]]);

test('audio shares the ninety-per-minute public Quran limiter', function () {
    for ($attempt = 0; $attempt < 90; $attempt++) {
        $this->getJson('/api/v1/quran/surahs/1/audio?reciter=invalid')->assertUnprocessable();
    }
    $this->getJson('/api/v1/quran/surahs/1/audio')->assertTooManyRequests()->assertHeader('Retry-After');
    Http::assertNothingSent();
});
