<?php

namespace App\Console\Commands;

use App\Services\Rca\NomenclatureService;
use App\Services\Rca\RcaException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

// Comanda din consola care porneste sincronizarea (php artisan rca:sync-nomenclature)
#[Signature('rca:sync-nomenclature')]
#[Description('Aduce judetele si localitatile din API-ul RCA si le salveaza local')]
class SyncNomenclature extends Command
{
    public function handle(NomenclatureService $nomenclature): int
    {
        $this->components->info('Sincronizez nomenclatorul de la '.config('rca.base_url'));

        try {
            $rezultat = $nomenclature->sync(function (string $judet, int $numar): void {
                $this->components->twoColumnDetail($judet, $numar.' localitati');
            });
        } catch (RcaException $e) {
            $this->components->error('Sincronizarea a esuat: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Gata: {$rezultat['counties']} judete, {$rezultat['localities']} localitati.");

        return self::SUCCESS;
    }
}
