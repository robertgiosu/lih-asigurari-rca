<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Identificatorul unic al actiunii curente.
 *
 * Un singur UUID pentru tot ce se intampla intr-un request web: randul din
 * audit_events si toate randurile din api_logs corespunzatoare primesc aceeasi valoare.
 */
final class Correlation // nu putem extinde clasa
{
    private static ?string $id = null;

    public static function id(): string // metoda statica
    {
        return self::$id ??= (string) Str::uuid(); // folosim operatorul ??= care inseamna "daca partea stanga e null, atribuie-i partea dreapta"
    }

    /** Folosit in teste, ca fiecare test sa porneasca de la zero. */
    public static function reset(): void
    {
        self::$id = null;
    }
}
