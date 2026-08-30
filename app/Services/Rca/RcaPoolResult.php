<?php

namespace App\Services\Rca;

/**
 * Rezultatul unui singur apel dintr-un pool.
 *
 * Spre deosebire de apelurile obisnuite, un esec aici NU e o exceptie: cand
 * interoghezi 11 asiguratori, caderea unuia e o informatie de afisat, nu un
 * motiv sa opresti tot.
 */
final class RcaPoolResult
{
    public function __construct(
        public readonly string $key,
        public readonly bool $ok,
        public readonly array $data = [],
        public readonly ?string $error = null,
        public readonly ?int $httpStatus = null,
        public readonly int $durationMs = 0,
    ) {
    }
}
