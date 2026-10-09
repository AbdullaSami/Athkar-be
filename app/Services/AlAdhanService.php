<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/** Map AlAdhan responses, cache only useful data, and keep coordinates out of storage and logs. */
class AlAdhanService
{
    public const CALCULATION_METHODS = [0, 1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23];

    /** @param array<string, mixed> $input Validated date, coordinates, timezone and supported calculation settings.
     * @return array<string, mixed> Local clock times paired with their explicit date and timezone.
     */
    public function prayerTimes(array $input): array
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $input['date']);
        $query = [...$this->coordinates($input), 'timezonestring' => $input['timezone'], 'iso8601' => 'false',
            'school' => (int) ($input['school'] ?? 0), 'latitudeAdjustmentMethod' => (int) ($input['high_latitude_method'] ?? 3),
            'shafaq' => $input['shafaq'] ?? 'general'];
        if (isset($input['calculation_method'])) {
            $query['method'] = (int) $input['calculation_method'];
        }

        return $this->cached('prayer_times', ['date' => $input['date'], ...$query], function () use ($date, $query, $input): array {
            $data = $this->request('timings/'.$date->format('d-m-Y'), $query, 'prayer_times');
            if (data_get($data, 'date.gregorian.date') !== $date->format('d-m-Y')
                || data_get($data, 'meta.timezone') !== $input['timezone']
                || ! is_int(data_get($data, 'meta.method.id'))
                || ! in_array(data_get($data, 'meta.method.id'), self::CALCULATION_METHODS, true)
                || (isset($query['method']) && data_get($data, 'meta.method.id') !== $query['method'])) {
                $this->unavailable('prayer_times', 'invalid_metadata');
            }
            $timings = [];
            foreach (['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $name) {
                $time = data_get($data, 'timings.'.$name);
                if (! is_string($time) || preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time) !== 1) {
                    $this->unavailable('prayer_times', 'invalid_timing');
                }
                $timings[strtolower($name)] = $time;
            }

            return ['date' => $input['date'], 'timezone' => $input['timezone'],
                'calculation_method' => data_get($data, 'meta.method.id'), 'school' => $query['school'],
                'high_latitude_method' => $query['latitudeAdjustmentMethod'], 'timings' => $timings];
        });
    }

    /** @param array<string, mixed> $input Validated Gregorian year/month and optional calendar method.
     * @return array<string, mixed> A complete month, bounded to 31 mapped days.
     */
    public function calendar(array $input): array
    {
        $year = (int) $input['year'];
        $month = (int) $input['month'];
        $method = $input['calendar_method'] ?? 'HJCoSA';

        return $this->cached('calendar', ['year' => $year, 'month' => $month, 'method' => $method], function () use ($year, $month, $method): array {
            $data = $this->request('gToHCalendar/'.$month.'/'.$year, ['calendarMethod' => $method], 'calendar');
            $start = CarbonImmutable::create($year, $month, 1);
            if (! array_is_list($data) || count($data) !== $start->daysInMonth) {
                $this->unavailable('calendar', 'incomplete_month');
            }
            $days = [];
            foreach ($data as $index => $entry) {
                $date = $start->addDays($index);
                $hijri = is_array($entry) ? ($entry['hijri'] ?? null) : null;
                if (! is_array($hijri) || data_get($entry, 'gregorian.date') !== $date->format('d-m-Y')) {
                    $this->unavailable('calendar', 'invalid_date');
                }
                $day = $this->integer($hijri['day'] ?? null, 1, 30);
                $hijriMonth = $this->integer(data_get($hijri, 'month.number'), 1, 12);
                $hijriYear = $this->integer($hijri['year'] ?? null, 1, 3000);
                $english = data_get($hijri, 'month.en');
                $arabic = data_get($hijri, 'month.ar');
                $holidays = $hijri['holidays'] ?? null;
                if ($day === null || $hijriMonth === null || $hijriYear === null
                    || ! $this->shortText($english) || ! $this->shortText($arabic)
                    || ! is_array($holidays) || ! array_is_list($holidays) || count($holidays) > 16) {
                    $this->unavailable('calendar', 'invalid_hijri');
                }
                foreach ($holidays as $holiday) {
                    if (! $this->shortText($holiday)) {
                        $this->unavailable('calendar', 'invalid_holiday');
                    }
                }
                $days[] = ['gregorian_date' => $date->format('Y-m-d'), 'hijri' => [
                    'day' => $day, 'month' => $hijriMonth, 'year' => $hijriYear,
                    'month_name' => ['en' => $english, 'ar' => $arabic], 'holidays' => $holidays,
                ]];
            }

            return ['year' => $year, 'month' => $month, 'calendar_method' => $method, 'days' => $days];
        });
    }

    /** @param array<string, mixed> $input Validated geographic coordinates.
     * @return array{direction: float, reference: string} Clockwise bearing from true north, not magnetic north.
     */
    public function qibla(array $input): array
    {
        $coordinates = $this->coordinates($input);

        return $this->cached('qibla', $coordinates, function () use ($coordinates): array {
            $data = $this->request('qibla/'.$coordinates['latitude'].'/'.$coordinates['longitude'], [], 'qibla');
            $direction = $data['direction'] ?? null;
            if ((! is_float($direction) && ! is_int($direction)) || ! is_finite((float) $direction) || $direction < 0 || $direction >= 360) {
                $this->unavailable('qibla', 'invalid_bearing');
            }

            return ['direction' => (float) $direction, 'reference' => 'true_north'];
        });
    }

    /** @param array<string, mixed> $input @return array{latitude: string, longitude: string} Canonical coordinates used only in memory and upstream requests. */
    private function coordinates(array $input): array
    {
        return ['latitude' => sprintf('%.6F', (float) $input['latitude']), 'longitude' => sprintf('%.6F', (float) $input['longitude'])];
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed> Opaque keyed cache identity; values never include coordinates or user IDs. */
    private function cached(string $feature, array $parameters, Closure $fetch): array
    {
        ksort($parameters);
        $identity = json_encode([$this->baseUrl(), $feature, $parameters], JSON_THROW_ON_ERROR);
        $key = 'aladhan:v1:'.$feature.':'.hash_hmac('sha256', $identity, (string) config('app.key'));
        $seconds = max(0, min(86400, (int) config('services.aladhan.cache_ttl.'.$feature, 0)));

        return Cache::remember($key, $seconds, $fetch);
    }

    /** @param array<string, mixed> $query @return array<mixed> Retry one safe GET only on connection failures or provider 5xx errors. */
    private function request(string $path, array $query, string $feature): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())->acceptJson()->withoutRedirecting()
                ->connectTimeout((int) config('services.aladhan.connect_timeout', 3))->timeout((int) config('services.aladhan.timeout', 8))
                ->retry(2, 100, fn (?Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->get($path, $query);
        } catch (ConnectionException) {
            $this->unavailable($feature, 'connection');
        }
        if (! $response->successful()) {
            $this->unavailable($feature, 'http_status', $response->status());
        }
        $body = $response->json();
        if (! is_array($body) || ($body['code'] ?? null) !== 200 || ! is_array($body['data'] ?? null)) {
            $this->unavailable($feature, 'invalid_response');
        }

        return $body['data'];
    }

    /** Permit a configured HTTPS endpoint; no caller controls host, credentials, redirects or query strings. */
    private function baseUrl(): string
    {
        $url = config('services.aladhan.base_url');
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ServiceUnavailableHttpException;
        }

        return rtrim($url, '/');
    }

    /** Parse bounded provider integer fields without accepting coercible arrays, decimals or booleans. */
    private function integer(mixed $value, int $minimum, int $maximum): ?int
    {
        if ((! is_int($value) && (! is_string($value) || preg_match('/^[0-9]{1,4}$/D', $value) !== 1))) {
            return null;
        }

        return (int) $value >= $minimum && (int) $value <= $maximum ? (int) $value : null;
    }

    /** Provider labels remain plain, bounded JSON strings; markup is unsupported. */
    private function shortText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= 160 && ! str_contains($value, "\0") && preg_match('/<\s*[a-z!\/?]/i', $value) !== 1;
    }

    /** Record only fixed operational context, never URLs, coordinates, user data or provider exception messages. */
    private function unavailable(string $feature, string $reason, ?int $status = null): never
    {
        Log::warning('AlAdhan request failed.', ['feature' => $feature, 'reason' => $reason, 'status' => $status]);

        throw new ServiceUnavailableHttpException;
    }
}
