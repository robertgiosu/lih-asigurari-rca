<?php

namespace App\Services\Rca;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Obtine si pastreaza tokenul JWT cerut de toate celelalte endpoint-uri.
 *
 * Strategie: token valid din cache -> reinnoire cu refresh_token ->
autentificare completa.
 */
class RcaTokenManager
{
    private const CACHE_KEY = 'rca.auth';

    /** Cu cate secunde inainte de expirare consideram tokenul inutilizabil.
     */
    private const SAFETY_MARGIN = 60;

    public function __construct(private readonly ApiCallLogger $logger)
    {
    }

    public function token(): string
    {
        $bundle = Cache::get(self::CACHE_KEY); // Bundle e pachetul salvat in cache: token, refresh token, data expirarii. Poate sa fie null daca e prima rulare sau a expirat.

        if (is_array($bundle) && $this->stillValid($bundle)) { // Am deja un tocket bun
            return $bundle['token'];
        }

        if (is_array($bundle) && ! empty($bundle['refresh_token'])) { // A expirat dar il pot reinnoi
            if ($renewed = $this->renew($bundle)) {
                return $renewed['token'];
            }
        }

        return $this->authenticate()['token']; // Facem tokenul de la zero cu authentificare
    }

    /** Apelata cand API-ul raspunde 401, ca urmatorul apel sa ceara token
    nou. */
    public function forget(): void // Sterge pachetul din cache, urmatorul apel la token() va gasi null si merge direct la autentificare
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function authenticate(): array // Construieste credentialele in query string, apoi face POST
    {
        $url = $this->url().'?'.http_build_query([
                'account'  => config('rca.account'),
                'password' => config('rca.password'),
            ]);

        return $this->store($this->call('POST', $url));
    }

    private function renew(array $bundle): ?array // PATCH in loc de POST - actualizezi o sesiune existenta, nu creezi una noua
    {
        try {
            return $this->store($this->call(
                'PATCH',
                $this->url(),
                ['refresh_token' => $bundle['refresh_token']],
                ['Token' => $bundle['token']],
            ));
        } catch (Throwable) {
            // Reinnoirea a esuat; apelantul cade inapoi pe autentificarea completa.
              return null;
          }
    }

    private function call(string $method, string $url, array $payload = [], array $headers = []): array // Metoda face un apel HTTP si il logheaza indiferent de rezultat
    {
        $started = hrtime(true);

        try {
            $response = $this->http()
                ->withHeaders($headers)
                ->send($method, $url, $payload ? ['json' => $payload] : []);
        } catch (Throwable $e) {
            $this->logger->log(
                method: $method,
                url: $url,
                requestBody: $payload ?: null,
                durationMs: $this->elapsed($started),
                error: $e->getMessage(),
            );

            throw new RuntimeException(
                'Nu s-a putut contacta serviciul de autentificare RCA: '.$e->getMessage(),
                previous: $e,
            );
        }

        $body = $response->json() ?? $response->body();

        $this->logger->log(
            method: $method,
            url: $url,
            requestBody: $payload ?: null,
            responseStatus: $response->status(),
            responseBody: $body,
            durationMs: $this->elapsed($started),
        );

        if (! $response->successful() || ! is_array($body) || ($body['error'] ?? true) || empty($body['data']['token'])) {
            throw new RuntimeException('Autentificarea la API-ul RCA a esuat: '.(is_array($body) && isset($body['message']) ? $body['message'] : 'raspuns neasteptat, status '.$response->status()));
        }

        return $body['data'];
    }

    private function store(array $data): array // Metoda ia raspunsul brut de la API, pastreaza doar ce trebuie si pune in cache
    {
        $bundle = [
            'token'         => $data['token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at'    => $this->expiresAt($data)?->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $bundle, now()->addDay());

        return $bundle;
    }

    private function stillValid(array $bundle): bool // verifica daca mai e valid token-ul
    {
        return ! empty($bundle['token']) && ! empty($bundle['expires_at']) && Carbon::parse($bundle['expires_at'])->subSeconds(self::SAFETY_MARGIN)->isFuture();
    }

    private function expiresAt(array $data): ?Carbon // Afla cand expira token-ul
    {
        // Sursa preferata: claim-ul 'exp' din JWT - epoch UTC, fara ambiguitati.
          if (! empty($data['token']) && $exp = $this->expiryFromJwt($data['token'])) {
              return $exp;
          }

          // Rezerva: campul text 'expires_at', care vine in ora Romaniei.
          return ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'], 'Europe/Bucharest') : null;
    }

    private function expiryFromJwt(string $jwt): ?Carbon // Deschide JWT-ul ssi citeste claim-ul exp din el
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return isset($payload['exp']) ? Carbon::createFromTimestamp((int) $payload['exp'], 'UTC') : null;
    }

    private function http(): PendingRequest // Configureaza cererea HTTP si ti-o da, dar netrimisa
    {
        return Http::asJson()
            ->acceptJson()
            ->withHeaders(['Content-Language' => config('rca.language')])
            ->timeout(config('rca.timeout'))
            ->connectTimeout(config('rca.connect_timeout'));
    }

    private function url(): string // Compune adresa endpoint-ului /auth
    {
        return rtrim(config('rca.base_url'), '/').'/auth';
    }

    private function elapsed(int|float $started): int // Calculeaza cate milisecunde a durat un apel
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
