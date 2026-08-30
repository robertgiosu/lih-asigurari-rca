<?php

namespace App\Services\Rca;

use App\Models\ApiLog;
use App\Support\Correlation;
use Illuminate\Support\Facades\Auth;

/**
 * Scrie in api_logs fiecare apel HTTP catre API-ul RCA.
 *
 * Folosit si de RcaTokenManager, si de RcaClient, ca sa existe un singur loc
 * in care se decide ce se mascheaza si ce se scurteaza.
 */
final class ApiCallLogger
{
    /** Chei al caror continut nu ajunge niciodata in baza de date. */
    private const SECRET_KEYS = ['password', 'token', 'refresh_token'];

    /** Peste atatea caractere, un string devine marcaj (ex. PDF-uri base64). */
    private const MAX_STRING = 2000;

    /** Dimensiunea maxima a unui corp serializat, in octeti. */
    private const MAX_BODY = 65536;

    public function log(
        string $method,
        string $url,
        ?array $requestHeaders = null,
        ?array $requestBody = null,
        ?int $responseStatus = null,
        mixed $responseBody = null,
        ?int $durationMs = null,
        ?string $error = null,
        ?string $provider = null,
        ?int $quoteRequestId = null,
    ): ApiLog {
        return ApiLog::create([
            'correlation_id'   => Correlation::id(),
            'user_id'          => Auth::id(),
            'quote_request_id' => $quoteRequestId,
            'provider'         => $provider,
            'method'           => strtoupper($method),
            'url'              => $this->maskUrl($url),
            'request_headers'  => $this->clean($requestHeaders),
            'request_body'     => $this->clean($requestBody),
            'response_status'  => $responseStatus,
            'response_body'    => $this->clean($responseBody),
            'duration_ms'      => $durationMs,
            'error'            => $error,
            'ip'               => request()->ip(),
        ]);
    }

    /** Parola calatoreste in query string la /auth, deci o ascundem si din URL. */
    private function maskUrl(string $url): string
    {
        return preg_replace('/(password=)[^&]*/i', '$1***', $url);
    }

    // functie care se ocupa de cazurile pe care walk nu le acopera (tot ce nu e array)
    private function clean(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null; // nu are sens sa scriem [] in DB pentru un body inexistent
        }

        if (! is_array($value)) {
            $value = ['_raw' => (string) $value]; // daca nu e array il impachetez
        }

        $cleaned = $this->walk($value);
        $encoded = json_encode($cleaned);

        if ($encoded === false || strlen($encoded) > self::MAX_BODY) { // verificam dimensiunea totala ( > 64 KB ?)
            return ['_truncated' => true, 'bytes' => strlen((string) $encoded)]; // daca e prea mare salvam un dictionar cu flag-ul _truncated true si cheia bytes cu valoare corespunzatoare a cator bytes sunt
        }

        return $cleaned;
    }

    // functia principala care se ocupa de mascarea parolelor in baza de date si are grija ca baza de date sa nu se umfle
    private function walk(array $data): array
    {
        foreach ($data as $key => $item) {
            if (in_array(strtolower((string) $key), self::SECRET_KEYS, true)) { // e cheie secreta?
                $data[$key] = '***'; // daca e atunci o codam

                continue;
            }

            if (is_array($item)) { // daca e array
                $data[$key] = $this->walk($item); // aici e recursivitate, se apeleaza pe sine pentru sub-array

                continue;
            }

            if (is_string($item) && strlen($item) > self::MAX_STRING) { // e string prea lung?
                $data[$key] = '<'.strlen($item).' octeti omisi>'; // daca are peste 2000 de caractere, string-ul e inlocuit cu un marcaj de tipul "<400000 octeti omisi>"
            }
        }

        return $data;
    }
}
