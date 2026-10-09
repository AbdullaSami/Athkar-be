<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/** Verify Google signatures locally; fetch only public signing certificates. */
class GoogleIdTokenService
{
    private const CERTIFICATES_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    /** @return array{sub: string, email: string, name: string, authoritative_email: bool} Verified account claims only. */
    public function verify(#[\SensitiveParameter] string $idToken): array
    {
        $clientIds = config('services.google.client_ids', []);
        if (! is_array($clientIds) || $clientIds === []) {
            throw new ServiceUnavailableHttpException;
        }

        $keys = [];
        foreach ($this->certificates() as $id => $certificate) {
            $keys[$id] = new Key($certificate, 'RS256');
        }

        try {
            $claims = (array) JWT::decode($idToken, $keys);
        } catch (Throwable) {
            $this->invalidToken();
        }

        if (! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! is_string($claims['aud'] ?? null) || ! in_array($claims['aud'], $clientIds, true)
            || (isset($claims['azp']) && ! in_array($claims['azp'], $clientIds, true))
            || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= now()->timestamp
            || ! is_int($claims['iat'] ?? null) || $claims['iat'] > now()->timestamp || $claims['iat'] >= $claims['exp']
            || (isset($claims['nbf']) && (! is_int($claims['nbf']) || $claims['nbf'] > now()->timestamp))
            || ! is_string($claims['sub'] ?? null) || preg_match('/^[A-Za-z0-9_-]{1,255}$/D', $claims['sub']) !== 1
            || ($claims['email_verified'] ?? null) !== true
            || ! is_string($claims['email'] ?? null) || strlen($claims['email']) > 254
            || ! filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
            || ! is_string($claims['name'] ?? null) || trim($claims['name']) === '' || mb_strlen($claims['name']) > 160) {
            $this->invalidToken();
        }

        return [
            'sub' => $claims['sub'], 'email' => Str::lower($claims['email']), 'name' => trim($claims['name']),
            'authoritative_email' => str_ends_with(Str::lower($claims['email']), '@gmail.com')
                || (is_string($claims['hd'] ?? null) && $claims['hd'] !== ''),
        ];
    }

    /** @return array<string, string> Cache validated RSA certificates for the provider's bounded lifetime. */
    private function certificates(): array
    {
        $cached = Cache::get('google:signing-certificates:v1');
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(5)
                ->withoutRedirecting()->get(self::CERTIFICATES_URL);
        } catch (ConnectionException) {
            throw new ServiceUnavailableHttpException;
        }

        $certificates = $response->json();
        if (! $response->successful() || ! is_array($certificates) || $certificates === [] || count($certificates) > 20) {
            throw new ServiceUnavailableHttpException;
        }

        foreach ($certificates as $id => $certificate) {
            if (! is_string($id) || ! is_string($certificate) || strlen($certificate) > 10000) {
                throw new ServiceUnavailableHttpException;
            }
            $key = openssl_pkey_get_public($certificate);
            $details = $key === false ? false : openssl_pkey_get_details($key);
            if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
                throw new ServiceUnavailableHttpException;
            }
        }

        preg_match('/(?:^|,)\s*max-age=(\d+)/i', $response->header('Cache-Control'), $matches);
        $seconds = max(0, min(3600, (int) ($matches[1] ?? 0) - (int) $response->header('Age')));
        if ($seconds > 0 && ! preg_match('/no-store|no-cache/i', $response->header('Cache-Control'))) {
            Cache::put('google:signing-certificates:v1', $certificates, $seconds);
        }

        return $certificates;
    }

    /** Reject credentials without exposing claims, token bytes, or verifier errors. */
    private function invalidToken(): never
    {
        throw ValidationException::withMessages(['id_token' => 'The Google credential is invalid.']);
    }
}
