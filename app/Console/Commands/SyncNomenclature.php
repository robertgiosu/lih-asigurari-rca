<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

// Comanda din consola care porneste sincronizarea (se ruleaza cu php artisan rca:sync-nomenclature)
#[Signature('app:sync-nomenclature')]
#[Description('Command description')]
class SyncNomenclature extends Command
{
    protected $signature = 'rca:sync-nomenclature';

    protected $description = 'Aduce judetele si localitatile din API-ul RCA si le salveaza local';

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
