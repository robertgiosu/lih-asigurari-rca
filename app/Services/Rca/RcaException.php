<?php

namespace App\Services\Rca;

use RuntimeException;

/**
 * Eroare venita de la API-ul RCA.
 *
 * Poarta cu ea contextul de care are nevoie interfata: ce status HTTP a fost,
 * ce a raspuns API-ul si pentru care asigurator.
 */
class RcaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?array $body = null,
        public readonly ?string $provider = null,
    ) {
        parent::__construct($message);
    }
}
