<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valideaza un CNP romanesc: format, data nasterii si cifra de control.
 *
 * Metodele statice sunt folosite si in afara validarii, ca sa deducem
 * sexul si data nasterii direct din CNP.
 */
class Cnp implements ValidationRule
{
    /** Ponderile oficiale pentru calculul cifrei de control. */
    private const WEIGHTS = [2, 7, 9, 1, 4, 6, 3, 5, 8, 2, 7, 9];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnp = (string) $value;

        if (! preg_match('/^\d{13}$/', $cnp)) {
            $fail('CNP-ul trebuie sa contina exact 13 cifre.');

            return;
        }

        if (self::birthdate($cnp) === null) {
            $fail('CNP-ul contine o data de nastere imposibila.');

            return;
        }

        if (! self::hasValidChecksum($cnp)) {
            $fail('CNP-ul nu este valid: cifra de control nu corespunde.');
        }
    }

    public static function isValid(string $cnp): bool
    {
        return preg_match('/^\d{13}$/', $cnp) === 1
            && self::birthdate($cnp) !== null
            && self::hasValidChecksum($cnp);
    }

    /** 'm' sau 'f'; null pentru persoanele straine (cifra 9), unde sexul nu e codificat. */
    public static function gender(string $cnp): ?string
    {
        return match ((int) ($cnp[0] ?? 0)) {
            1, 3, 5, 7 => 'm',
            2, 4, 6, 8 => 'f',
            default    => null,
        };
    }

    /** Data nasterii in format Y-m-d, sau null daca cifrele nu formeaza o data reala. */
    public static function birthdate(string $cnp): ?string
    {
        if (! preg_match('/^\d{13}$/', $cnp)) {
            return null;
        }

        // Prima cifra codifica secolul. 7, 8 si 9 nu il precizeaza, deci incercam ambele.
        $centuries = match ((int) $cnp[0]) {
            1, 2       => [1900],
            3, 4       => [1800],
            5, 6       => [2000],
            7, 8, 9    => [2000, 1900],
            default    => [],
        };

        $year  = (int) substr($cnp, 1, 2);
        $month = (int) substr($cnp, 3, 2);
        $day   = (int) substr($cnp, 5, 2);

        foreach ($centuries as $century) {
            $full = $century + $year;

            if (checkdate($month, $day, $full) && $full <= (int) date('Y')) {
                return sprintf('%04d-%02d-%02d', $full, $month, $day);
            }
        }

        return null;
    }

    private static function hasValidChecksum(string $cnp): bool
    {
        $sum = 0;

        foreach (self::WEIGHTS as $position => $weight) {
            $sum += (int) $cnp[$position] * $weight;
        }

        $control = $sum % 11;

        return ($control === 10 ? 1 : $control) === (int) $cnp[12];
    }
}
