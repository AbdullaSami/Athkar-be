<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/** Validate and map Quran provider data without persisting scripture in application tables. */
class QuranService
{
    public const EDITIONS = ['quran-uthmani', 'en.sahih'];

    public const TRANSLATION_EDITIONS = ['en.sahih'];

    public const RECITERS = ['ar.alafasy'];

    /** Return a bounded page of cached, validated surah metadata. */
    public function surahs(int $page, int $perPage): array
    {
        return $this->paginate($this->metadata(), $page, $perPage);
    }

    /** Return one surah's metadata, never a provider model or raw payload. */
    public function surah(int $number): array
    {
        return $this->metadata()[$number - 1];
    }

    /** Return a bounded page of text from one supported edition, preserving text bytes. */
    public function ayahs(int $number, string $edition, int $page, int $perPage): array
    {
        $metadata = $this->metadata();
        $surah = $metadata[$number - 1];
        $firstGlobalNumber = 1 + array_sum(array_column(array_slice($metadata, 0, $number - 1), 'ayah_count'));
        $ayahs = $this->cached('ayahs', [$number, $edition], function () use ($number, $edition, $surah, $firstGlobalNumber): array {
            $data = $this->request('surah/'.$number.'/'.$edition);
            $entries = $data['ayahs'] ?? null;
            if (($data['number'] ?? null) !== $number || ($data['numberOfAyahs'] ?? null) !== $surah['ayah_count']
                || data_get($data, 'edition.identifier') !== $edition || data_get($data, 'edition.format') !== 'text'
                || ! is_array($entries) || ! array_is_list($entries) || count($entries) !== $surah['ayah_count']) {
                $this->unavailable('invalid_surah');
            }
            $mapped = [];
            foreach ($entries as $index => $entry) {
                if (! is_array($entry) || ($entry['numberInSurah'] ?? null) !== $index + 1
                    || ! is_int($entry['number'] ?? null) || $entry['number'] !== $firstGlobalNumber + $index || $entry['number'] > 6236
                    || ! $this->plainText($entry['text'] ?? null, 20000)) {
                    $this->unavailable('invalid_ayah');
                }
                $mapped[] = ['ayah_number' => $entry['numberInSurah'], 'global_number' => $entry['number'], 'text' => $entry['text']];
            }

            return $mapped;
        });

        return [...$this->paginate($ayahs, $page, $perPage), 'surah' => $surah, 'edition' => $edition];
    }

    /** Return paginated, validated CDN audio URLs for one supported reciter. */
    public function audio(int $number, string $reciter, int $page, int $perPage): array
    {
        $metadata = $this->metadata();
        $surah = $metadata[$number - 1];
        $firstGlobalNumber = 1 + array_sum(array_column(array_slice($metadata, 0, $number - 1), 'ayah_count'));
        $audio = $this->cached('audio', [$number, $reciter], function () use ($number, $reciter, $surah, $firstGlobalNumber): array {
            $data = $this->request('surah/'.$number.'/'.$reciter);
            $entries = $data['ayahs'] ?? null;
            if (($data['number'] ?? null) !== $number || ($data['numberOfAyahs'] ?? null) !== $surah['ayah_count']
                || data_get($data, 'edition.identifier') !== $reciter || data_get($data, 'edition.format') !== 'audio'
                || ! is_array($entries) || ! array_is_list($entries) || count($entries) !== $surah['ayah_count']) {
                $this->unavailable('invalid_audio_surah');
            }
            $mapped = [];
            foreach ($entries as $index => $entry) {
                $globalNumber = $firstGlobalNumber + $index;
                if (! is_array($entry) || ($entry['numberInSurah'] ?? null) !== $index + 1
                    || ($entry['number'] ?? null) !== $globalNumber || $globalNumber > 6236
                    || ! $this->audioUrl($entry['audio'] ?? null, $reciter, $globalNumber)) {
                    $this->unavailable('invalid_audio_ayah');
                }
                $mapped[] = ['ayah_number' => $index + 1, 'global_number' => $globalNumber, 'audio_url' => $entry['audio']];
            }

            return $mapped;
        });

        return [...$this->paginate($audio, $page, $perPage), 'surah' => $surah, 'reciter' => $reciter];
    }

    /** Accept only the documented HTTPS CDN path for the requested reciter and exact ayah. */
    private function audioUrl(mixed $url, string $reciter, int $number): bool
    {
        return is_string($url) && strlen($url) <= 512
            && preg_match('~^https://cdn\.islamic\.network/quran/audio/(?:192|128|64|48|40|32)/'.preg_quote($reciter, '~').'/'.$number.'\.mp3$~D', $url) === 1;
    }

    /** Return exactly 114 ordered metadata records; cache only the application mapping. */
    private function metadata(): array
    {
        return $this->cached('metadata', [], function (): array {
            $data = $this->request('surah');
            if (! array_is_list($data) || count($data) !== 114) {
                $this->unavailable('invalid_metadata');
            }
            $mapped = [];
            foreach ($data as $index => $entry) {
                if (! is_array($entry) || ($entry['number'] ?? null) !== $index + 1
                    || ! is_int($entry['numberOfAyahs'] ?? null) || $entry['numberOfAyahs'] < 1 || $entry['numberOfAyahs'] > 286
                    || ! in_array($entry['revelationType'] ?? null, ['Meccan', 'Medinan'], true)
                    || ! $this->plainText($entry['name'] ?? null, 160)
                    || ! $this->plainText($entry['englishName'] ?? null, 160)
                    || ! $this->plainText($entry['englishNameTranslation'] ?? null, 160)) {
                    $this->unavailable('invalid_metadata');
                }
                $mapped[] = ['number' => $entry['number'], 'name' => $entry['name'], 'english_name' => $entry['englishName'],
                    'english_name_translation' => $entry['englishNameTranslation'], 'ayah_count' => $entry['numberOfAyahs'],
                    'revelation_type' => strtolower($entry['revelationType'])];
            }

            return $mapped;
        });
    }

    /** @param array<int, array<string, mixed>> $items Return data and predictable pagination metadata. */
    private function paginate(array $items, int $page, int $perPage): array
    {
        return ['data' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => count($items), 'last_page' => (int) ceil(count($items) / $perPage)]];
    }

    /** Cache normalized static content with a bounded TTL and provider-specific key. */
    private function cached(string $feature, array $parameters, Closure $fetch): array
    {
        $identity = json_encode([$this->baseUrl(), $parameters], JSON_THROW_ON_ERROR);
        $seconds = max(0, min(604800, (int) config('services.quran.cache_ttl.'.$feature, 86400)));

        return Cache::remember('quran:v1:'.$feature.':'.hash('sha256', $identity), $seconds, $fetch);
    }

    /** Request one safe GET, retrying only transient connection or server failures once. */
    private function request(string $path): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())->acceptJson()->withoutRedirecting()
                ->connectTimeout((int) config('services.quran.connect_timeout', 3))
                ->timeout((int) config('services.quran.timeout', 8))
                ->retry(2, 100, fn (?Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->get($path);
        } catch (ConnectionException) {
            $this->unavailable('connection');
        }
        if (! $response->successful()) {
            $this->unavailable('http_status', $response->status());
        }
        $body = $response->json();
        if (! is_array($body) || ($body['code'] ?? null) !== 200 || ! is_array($body['data'] ?? null)) {
            $this->unavailable('invalid_response');
        }

        return $body['data'];
    }

    /** Only deployment configuration controls the HTTPS host; redirects and credentials are forbidden. */
    private function baseUrl(): string
    {
        $url = config('services.quran.base_url');
        $parts = is_string($url) ? parse_url($url) : false;
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ServiceUnavailableHttpException;
        }

        return rtrim($url, '/');
    }

    /** Reject malformed or executable provider text without stripping Quran whitespace or diacritics. */
    private function plainText(mixed $value, int $maximum): bool
    {
        return is_string($value) && mb_check_encoding($value, 'UTF-8') && trim($value) !== ''
            && mb_strlen($value) <= $maximum && ! str_contains($value, "\0") && preg_match('/<\s*[a-z!\/?]/i', $value) !== 1;
    }

    /** Log fixed operational context only; never raw provider bodies, URLs or exceptions. */
    private function unavailable(string $reason, ?int $status = null): never
    {
        Log::warning('Quran provider request failed.', ['reason' => $reason, 'status' => $status]);

        throw new ServiceUnavailableHttpException;
    }
}
