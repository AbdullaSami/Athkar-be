<?php

use App\Models\User;
use Carbon\CarbonImmutable;
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

/** @return array<string, mixed> Explicit synthetic location/date inputs. */
function aladhanPrayerInput(array $changes = []): array
{
    return array_replace(['latitude' => 30.0444, 'longitude' => 31.2357, 'date' => '2026-10-09',
        'timezone' => 'Africa/Cairo', 'calculation_method' => 5], $changes);
}

/** @return array<string, mixed> Synthetic prayer fixture with private provider extras to exclude. */
function aladhanPrayerFixture(): array
{
    return ['code' => 200, 'status' => 'OK', 'data' => [
        'timings' => ['Fajr' => '05:30', 'Sunrise' => '06:45', 'Dhuhr' => '12:45', 'Asr' => '16:00', 'Maghrib' => '18:30', 'Isha' => '20:00'],
        'date' => ['gregorian' => ['date' => '09-10-2026']],
        'meta' => ['timezone' => 'Africa/Cairo', 'method' => ['id' => 5, 'name' => 'Provider label'],
            'latitude' => 30.0444, 'longitude' => 31.2357, 'private_marker' => 'provider-only-marker'],
    ]];
}

/** @return array<string, mixed> A complete synthetic Gregorian-to-Hijri month, not religious reference data. */
function aladhanCalendarFixture(int $year = 2026, int $month = 10): array
{
    $start = CarbonImmutable::create($year, $month, 1);
    $days = [];
    for ($day = 0; $day < $start->daysInMonth; $day++) {
        $days[] = ['gregorian' => ['date' => $start->addDays($day)->format('d-m-Y')], 'hijri' => [
            'day' => (string) (($day % 30) + 1), 'month' => ['number' => 4, 'en' => 'Test month', 'ar' => 'شهر تجريبي'],
            'year' => '1448', 'holidays' => $day === 0 ? ['Test event'] : [], 'private_marker' => 'provider-only-marker',
        ]];
    }

    return ['code' => 200, 'data' => $days];
}

/** Form a GET path with URL-encoded validated input. */
function aladhanPath(string $feature, array $input): string
{
    return '/api/v1/'.$feature.'?'.http_build_query($input);
}

test('prayer times map provider data and cache only the application response', function () {
    Http::fake(['api.aladhan.com/v1/timings/*' => Http::response(aladhanPrayerFixture())]);
    $path = aladhanPath('prayer-times', aladhanPrayerInput(['school' => 1, 'high_latitude_method' => 2, 'shafaq' => 'ahmer']));

    $this->getJson($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['data' => [
        'date' => '2026-10-09', 'timezone' => 'Africa/Cairo', 'calculation_method' => 5, 'school' => 1, 'high_latitude_method' => 2,
        'timings' => ['fajr' => '05:30', 'sunrise' => '06:45', 'dhuhr' => '12:45', 'asr' => '16:00', 'maghrib' => '18:30', 'isha' => '20:00'],
    ]]);
    $this->getJson($path)->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/timings/09-10-2026')
        && $request['latitude'] === '30.044400' && $request['longitude'] === '31.235700'
        && $request['method'] === 5 && $request['school'] === 1 && $request['latitudeAdjustmentMethod'] === 2
        && $request['timezonestring'] === 'Africa/Cairo' && $request['shafaq'] === 'ahmer' && $request['iso8601'] === 'false');
    $this->assertDatabaseCount('prayer_settings', 0);
});

test('Qibla returns a numeric true north bearing and excludes coordinates', function () {
    Http::fake(['api.aladhan.com/v1/qibla/*' => Http::response(['code' => 200, 'data' => [
        'latitude' => 30.0444, 'longitude' => 31.2357, 'direction' => 136.5, 'private_marker' => 'provider-only-marker',
    ]])]);
    $path = aladhanPath('qibla', ['latitude' => 30.0444, 'longitude' => 31.2357]);

    $this->getJson($path)->assertOk()->assertExactJson(['data' => ['direction' => 136.5, 'reference' => 'true_north']]);
    $this->getJson($path)->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.aladhan.com/v1/qibla/30.044400/31.235700');
});

test('monthly Islamic calendar returns complete mapped days and Arabic labels', function (int $year, int $month, int $count) {
    Http::fake(['api.aladhan.com/v1/gToHCalendar/*' => Http::response(aladhanCalendarFixture($year, $month))]);
    $path = aladhanPath('islamic-calendar', ['year' => $year, 'month' => $month, 'calendar_method' => 'UAQ']);

    $response = $this->getJson($path)->assertOk()->assertJsonCount($count, 'data.days')->assertJsonPath('data.calendar_method', 'UAQ')
        ->assertJsonPath('data.days.0.hijri.month_name.ar', 'شهر تجريبي')->assertJsonPath('data.days.0.hijri.day', 1)
        ->assertJsonPath('data.days.0.hijri.year', 1448)->assertJsonPath('data.days.0.hijri.holidays.0', 'Test event')
        ->assertDontSee('provider-only-marker');
    expect($response->json('data.days.0.gregorian_date'))->toBe(sprintf('%04d-%02d-01', $year, $month));
    $this->getJson($path)->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/gToHCalendar/'.$month.'/'.$year) && $request['calendarMethod'] === 'UAQ');
})->with([[2026, 10, 31], [2026, 2, 28], [2024, 2, 29]]);

test('invalid feature input receives HTTP 422 before any upstream request', function (string $feature, array $input, string $field) {
    $this->getJson(aladhanPath($feature, $input))->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([
    'missing latitude' => ['qibla', ['longitude' => 0], 'latitude'],
    'latitude range' => ['qibla', ['latitude' => 90.1, 'longitude' => 0], 'latitude'],
    'longitude range' => ['qibla', ['latitude' => 0, 'longitude' => -180.1], 'longitude'],
    'coordinate array' => ['qibla', ['latitude' => [0], 'longitude' => 0], 'latitude'],
    'coordinate NaN' => ['qibla', ['latitude' => 'NaN', 'longitude' => 0], 'latitude'],
    'URL injection' => ['qibla', ['latitude' => 0, 'longitude' => 0, 'url' => 'https://attacker.example'], 'url'],
    'wrong date format' => ['prayer-times', aladhanPrayerInput(['date' => '09-10-2026']), 'date'],
    'impossible date' => ['prayer-times', aladhanPrayerInput(['date' => '2026-02-30']), 'date'],
    'old date' => ['prayer-times', aladhanPrayerInput(['date' => '1999-12-31']), 'date'],
    'future date' => ['prayer-times', aladhanPrayerInput(['date' => '2101-01-01']), 'date'],
    'timezone' => ['prayer-times', aladhanPrayerInput(['timezone' => 'not/a-zone']), 'timezone'],
    'timezone array' => ['prayer-times', aladhanPrayerInput(['timezone' => ['UTC']]), 'timezone'],
    'custom method unsupported' => ['prayer-times', aladhanPrayerInput(['calculation_method' => 99]), 'calculation_method'],
    'undefined method' => ['prayer-times', aladhanPrayerInput(['calculation_method' => 6]), 'calculation_method'],
    'invalid school' => ['prayer-times', aladhanPrayerInput(['school' => 2]), 'school'],
    'invalid latitude method' => ['prayer-times', aladhanPrayerInput(['high_latitude_method' => 0]), 'high_latitude_method'],
    'invalid shafaq' => ['prayer-times', aladhanPrayerInput(['shafaq' => 'unsupported']), 'shafaq'],
    'invalid month' => ['islamic-calendar', ['year' => 2026, 'month' => 13], 'month'],
    'invalid year' => ['islamic-calendar', ['year' => 2101, 'month' => 1], 'year'],
    'array year' => ['islamic-calendar', ['year' => [2026], 'month' => 1], 'year'],
    'invalid calendar method' => ['islamic-calendar', ['year' => 2026, 'month' => 1, 'calendar_method' => 'unknown'], 'calendar_method'],
]);

test('provider errors return safe HTTP 503 with bounded retries and sanitized logs', function (string $failure, int $attempts) {
    Sleep::fake();
    Log::spy();
    $response = match ($failure) {
        'connection' => Http::failedConnection(),
        'server' => Http::response('provider secret marker', 500),
        'client' => Http::response('provider secret marker', 400),
        'rate' => Http::response('provider secret marker', 429),
        'redirect' => Http::response('', 302, ['Location' => 'https://attacker.example']),
        'json' => Http::response('not-json', 200),
        'code' => Http::response(['code' => 500, 'data' => ['direction' => 1]]),
        'missing data' => Http::response(['code' => 200]),
    };
    Http::fake(['api.aladhan.com/v1/qibla/*' => $response]);

    $this->getJson(aladhanPath('qibla', ['latitude' => 30.0444, 'longitude' => 31.2357]))
        ->assertStatus(503)->assertExactJson(['message' => 'Server error.']);
    Http::assertSentCount($attempts);
    Log::shouldHaveReceived('warning')->once()->with('AlAdhan request failed.', Mockery::on(fn ($context) => array_keys($context) === ['feature', 'reason', 'status'] && $context['feature'] === 'qibla'
        && ! str_contains(json_encode($context), '30.0444') && ! str_contains(json_encode($context), 'provider secret marker')
    ));
})->with([['connection', 2], ['server', 2], ['client', 1], ['rate', 1], ['redirect', 1], ['json', 1], ['code', 1], ['missing data', 1]]);

test('transient upstream failures retry once and return the recovered result', function () {
    Sleep::fake();
    Http::fake(['api.aladhan.com/v1/qibla/*' => Http::sequence()->push([], 503)->push(['code' => 200, 'data' => ['direction' => 90]])]);

    $this->getJson(aladhanPath('qibla', ['latitude' => 0, 'longitude' => 0]))->assertOk()->assertJsonPath('data.direction', 90);
    Http::assertSentCount(2);
});

test('invalid Qibla bearings receive HTTP 503', function (mixed $direction) {
    Http::fake(['api.aladhan.com/v1/qibla/*' => Http::response(['code' => 200, 'data' => ['direction' => $direction]])]);

    $this->getJson(aladhanPath('qibla', ['latitude' => 0, 'longitude' => 0]))->assertStatus(503);
    Http::assertSentCount(1);
})->with([null, -1, 360, '90', ['invalid']]);

test('malformed prayer responses receive HTTP 503 and are not cached', function (string $field, mixed $value) {
    $body = aladhanPrayerFixture();
    data_set($body, $field, $value);
    Http::fake(['api.aladhan.com/v1/timings/*' => Http::response($body)]);
    $path = aladhanPath('prayer-times', aladhanPrayerInput());

    $this->getJson($path)->assertStatus(503)->assertExactJson(['message' => 'Server error.']);
    $this->getJson($path)->assertStatus(503);
    Http::assertSentCount(2);
})->with([
    ['data.timings.Fajr', null], ['data.timings.Isha', '24:00'], ['data.timings.Asr', ['16:00']],
    ['data.date.gregorian.date', '10-10-2026'], ['data.meta.timezone', 'UTC'], ['data.meta.method.id', 99],
]);

test('invalid calendar entries and incomplete months receive HTTP 503', function (string $failure) {
    $body = aladhanCalendarFixture();
    match ($failure) {
        'incomplete' => array_pop($body['data']),
        'wrong date' => data_set($body, 'data.1.gregorian.date', '01-10-2026'),
        'invalid month' => data_set($body, 'data.0.hijri.month.number', 13),
        'invalid day' => data_set($body, 'data.0.hijri.day', '31'),
        'invalid year' => data_set($body, 'data.0.hijri.year', ['1448']),
        'HTML label' => data_set($body, 'data.0.hijri.month.en', '<script>alert(1)</script>'),
        'malformed holiday' => data_set($body, 'data.0.hijri.holidays', [['unexpected' => 'array']]),
    };
    Http::fake(['api.aladhan.com/v1/gToHCalendar/*' => Http::response($body)]);

    $this->getJson(aladhanPath('islamic-calendar', ['year' => 2026, 'month' => 10]))->assertStatus(503);
    Http::assertSentCount(1);
})->with(['incomplete', 'wrong date', 'invalid month', 'invalid day', 'invalid year', 'HTML label', 'malformed holiday']);

test('cache separates calculation settings and expires at its configured lifetime', function () {
    $this->freezeTime();
    config(['services.aladhan.cache_ttl.prayer_times' => 1]);
    Http::fake(['api.aladhan.com/v1/timings/*' => Http::response(aladhanPrayerFixture())]);

    $this->getJson(aladhanPath('prayer-times', aladhanPrayerInput()))->assertOk();
    $this->getJson(aladhanPath('prayer-times', aladhanPrayerInput(['school' => 1])))->assertOk();
    $this->travel(2)->seconds();
    $this->getJson(aladhanPath('prayer-times', aladhanPrayerInput()))->assertOk();
    Http::assertSentCount(3);
});

test('coordinate encodings normalize to one cache entry without persisting coordinates', function () {
    Http::fake(['api.aladhan.com/v1/qibla/*' => Http::response(['code' => 200, 'data' => ['direction' => 90]])]);
    $this->getJson(aladhanPath('qibla', ['latitude' => '30.044400', 'longitude' => '31.235700']))->assertOk();
    $this->getJson(aladhanPath('qibla', ['longitude' => 31.2357, 'latitude' => 30.0444]))->assertOk();

    Http::assertSentCount(1);
    $this->assertDatabaseCount('prayer_settings', 0);
    $this->assertDatabaseCount('users', 0);
});

test('unsafe provider configuration fails closed before any network request', function (string $url) {
    config(['services.aladhan.base_url' => $url]);

    $this->getJson(aladhanPath('qibla', ['latitude' => 0, 'longitude' => 0]))->assertStatus(503);
    Http::assertNothingSent();
})->with(['http://api.aladhan.com/v1', 'https://user:pass@api.aladhan.com/v1', 'https://api.aladhan.com/v1?secret=1', 'https://api.aladhan.com/v1#fragment']);

test('each public proxy has its own named rate limit', function (string $feature, int $limit) {
    for ($attempt = 0; $attempt < $limit; $attempt++) {
        $this->getJson('/api/v1/'.$feature)->assertUnprocessable();
    }
    $this->getJson('/api/v1/'.$feature)->assertTooManyRequests()->assertHeader('Retry-After');
    Http::assertNothingSent();
})->with([['prayer-times', 60], ['islamic-calendar', 60], ['qibla', 30]]);

test('proxy rate limits use authenticated user IDs instead of a shared IP', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->withToken($first->createToken('mobile', ['mobile'])->plainTextToken);
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->getJson('/api/v1/qibla')->assertUnprocessable();
    }
    $this->getJson('/api/v1/qibla')->assertTooManyRequests();
    Auth::forgetGuards();
    $this->withToken($second->createToken('mobile', ['mobile'])->plainTextToken)->getJson('/api/v1/qibla')->assertUnprocessable();
    Http::assertNothingSent();
});
