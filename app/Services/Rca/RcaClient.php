<?php

namespace App\Services\Rca;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Pool;

/**
 * Punctul unic prin care trec toate apelurile catre API-ul RCA,
 * cu exceptia autentificarii (vezi RcaTokenManager).
 */
class RcaClient
{
    public function __construct(
        private readonly RcaTokenManager $tokens,
        private readonly ApiCallLogger $logger,
    ) {
    }

    /** @return array Continutul lui 'data' din raspuns. */
    public function get(string $path, array $query = [], ?int $quoteRequestId = null): array
    {
        return $this->call('GET', $path, query: $query, quoteRequestId: $quoteRequestId);
    }

    /** @return array Continutul lui 'data' din raspuns. */
    public function post(string $path, array $payload = [], ?string $provider = null, ?int $quoteRequestId =
    null): array
    {
        return $this->call('POST', $path, payload: $payload, provider: $provider, quoteRequestId:
            $quoteRequestId);
    }

    /**
     * Trimite mai multe apeluri simultan.
     *
     * @param  array<string, array{path: string, payload: array}>  $calls
     * @return array<string, RcaPoolResult>  aceleasi chei ca $calls
     */
    public function pool(array $calls, ?int $quoteRequestId = null): array
    {
        // Un singur token pentru toate apelurile: altfel s-ar autentifica fiecare separat.
        $headers = $this->headers();

        $durations = [];

        $responses = Http::pool(function (Pool $pool) use ($calls, $headers, &$durations) {
            foreach ($calls as $key => $call) {
                $pool->as($key)
                    ->asJson()
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->timeout(config('rca.timeout'))
                    ->connectTimeout(config('rca.connect_timeout'))
                    // Singurul mod de a sti cat a durat fiecare apel in parte.
                    ->withOptions(['on_stats' => function (TransferStats $stats) use (&$durations, $key) {
                        $durations[$key] = (int) round($stats->getTransferTime() * 1000);
                    }])
                    ->post($this->url($call['path']), $call['payload']);
            }
        });

        $results = [];
        $sawUnauthorized = false;

        foreach ($calls as $key => $call) {
            $results[$key] = $this->interpret(
                $key,
                $call,
                $responses[$key] ?? null,
                $headers,
                $durations[$key] ?? 0,
                $quoteRequestId,
            );

            $sawUnauthorized = $sawUnauthorized || $results[$key]->httpStatus === 401;
        }

        // Nu reincercam intreg pool-ul, dar aruncam tokenul ca urmatoarea cerere sa ia unul nou.
        if ($sawUnauthorized) {
            $this->tokens->forget();
        }

        return $results;
    }

    /**
     * Transforma raspunsul brut (Response sau exceptie) intr-un RcaPoolResult si il logheaza.
     */
    private function interpret(
        string $key,
        array $call,
        mixed $response,
        array $headers,
        int $durationMs,
        ?int $quoteRequestId,
    ): RcaPoolResult {
        $url = $this->url($call['path']);

        // Http::pool nu arunca exceptiile: le pune in array in locul raspunsului.
        if ($response instanceof Throwable) {
            $this->logger->log(
                method: 'POST',
                url: $url,
                requestHeaders: $headers,
                requestBody: $call['payload'],
                durationMs: $durationMs,
                error: $response->getMessage(),
                provider: $key,
                quoteRequestId: $quoteRequestId,
            );

            return new RcaPoolResult($key, false, error: 'Serviciul nu a raspuns: '.$response->getMessage(),
                durationMs: $durationMs);
        }

        $this->logger->log(
            method: 'POST',
            url: $url,
            requestHeaders: $headers,
            requestBody: $call['payload'],
            responseStatus: $response->status(),
            responseBody: $response->json() ?? $response->body(),
            durationMs: $durationMs,
            provider: $key,
            quoteRequestId: $quoteRequestId,
        );

        try {
            $data = $this->unwrap($response, $key);
        } catch (RcaException $e) {
            return new RcaPoolResult($key, false, error: $e->getMessage(), httpStatus: $e->httpStatus, durationMs:
                $durationMs);
        }

        return new RcaPoolResult($key, true, data: $data, httpStatus: $response->status(), durationMs:
            $durationMs);
    }

    private function call(
        string $method,
        string $path,
        array $query = [],
        array $payload = [],
        ?string $provider = null,
        ?int $quoteRequestId = null,
        bool $isRetry = false,
    ): array {
        $url = $this->url($path, $query);

        // Obtinut inainte de try: daca autentificarea esueaza, eroarea vine de la
        // RcaTokenManager (care si-a logat deja apelul), nu de la apelul de fata.
        $headers = $this->headers();

        $started = hrtime(true);

        try {
            $response = $this->request($headers)->send($method, $url, $payload ? ['json' => $payload] : []);
        } catch (Throwable $e) {
            $this->logger->log(
                method: $method,
                url: $url,
                requestHeaders: $headers,
                requestBody: $payload ?: null,
                durationMs: $this->elapsed($started),
                error: $e->getMessage(),
                provider: $provider,
                quoteRequestId: $quoteRequestId,
            );

            throw new RcaException(
                'Serviciul RCA nu a putut fi contactat: '.$e->getMessage(),
                provider: $provider,
            );
        }

        $this->logger->log(
            method: $method,
            url: $url,
            requestHeaders: $headers,
            requestBody: $payload ?: null,
            responseStatus: $response->status(),
            responseBody: $response->json() ?? $response->body(),
            durationMs: $this->elapsed($started),
            provider: $provider,
            quoteRequestId: $quoteRequestId,
        );

        // Tokenul a fost invalidat de partea lor: il aruncam si reincercam o singura data.
        if ($response->status() === 401 && ! $isRetry) {
            $this->tokens->forget();

            return $this->call($method, $path, $query, $payload, $provider, $quoteRequestId, isRetry: true);
        }

        return $this->unwrap($response, $provider);
    }

    /**
     * Desface plicul {error, status, data} in care API-ul ambaleaza orice raspuns.
     */
    private function unwrap(Response $response, ?string $provider): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new RcaException(
                'Raspuns neasteptat de la API (status '.$response->status().')',
                httpStatus: $response->status(),
                provider: $provider,
            );
        }

        if (! $response->successful() || ($body['error'] ?? false)) {
            throw new RcaException(
                $body['message'] ?? 'Eroare API, status '.$response->status(),
                httpStatus: $response->status(),
                body: $body,
                provider: $provider,
            );
        }

        return $body['data'] ?? [];
    }

    private function headers(): array
    {
        return [
            'Token'            => $this->tokens->token(),
            'Content-Language' => config('rca.language'),
        ];
    }

    private function request(array $headers): PendingRequest // construieste request-ul
    {
        return Http::asJson()
            ->acceptJson()
            ->withHeaders($headers)
            ->timeout(config('rca.timeout'))
            ->connectTimeout(config('rca.connect_timeout'));
    }

    private function url(string $path, array $query = []): string
    {
        $url = rtrim(config('rca.base_url'), '/').'/'.ltrim($path, '/');

        return $query ? $url.'?'.http_build_query($query) : $url;
    }

    private function elapsed(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
