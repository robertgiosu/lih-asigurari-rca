<?php

namespace App\Services\Rca;

use App\Models\County;
use App\Models\Locality;
use Illuminate\Support\Collection;

class NomenclatureService
{
    /** Cate randuri trimitem intr-un singur upsert. */
    private const CHUNK = 100;

    public function __construct(private readonly RcaClient $client)
    {
    }

    /**
     * Aduce judetele si localitatile de la API si le scrie local.
     *
     * @param  callable|null  $progress  primeste (codJudet, nrLocalitati) dupa fiecare judet
     * @return array{counties: int, localities: int}
     */
    public function sync(?callable $progress = null): array
    {
        $counties = collect($this->client->get('/nomenclature/county')) // collect transforma array-ul brut intr-o colectie Laravel
            ->map(fn (array $c) => [
                'code'       => $c['code'],
                'name'       => $c['name'],
                'siruta'     => $c['siruta'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        County::upsert($counties->all(), ['code'], ['name', 'siruta', 'updated_at']); // Cele trei argumente: 1. randurile de scris, 2. coloanele dupa care se identifica duplicatele (code), 3. ce se actualizeaza daca exista deja randul

        $total = 0;

        foreach ($counties as $county) {
            $rows = collect($this->client->get('/nomenclature/locality/'.$county['code']))
                ->keyBy('siruta') // elimina duplicatele dupa siruita
                ->map(fn (array $l, int|string $siruta) => [
                    'county_code' => $l['countyCode'] ?? $county['code'],
                    'name'        => $l['name'],
                    'rang'        => $l['rang'] ?? null,
                    'siruta'      => (int) $siruta,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ])
                ->values();

            foreach ($rows->chunk(self::CHUNK) as $chunk) { // chunk sparge in bucati
                Locality::upsert($chunk->all(), ['county_code', 'siruta'], ['name', 'rang', 'updated_at']);
            }

            $total += $rows->count();

            if ($progress !== null) {
                $progress($county['code'], $rows->count());
            }
        }

        return ['counties' => $counties->count(), 'localities' => $total]; // Returneaza cate judete si cate localitati au trecut prin proces.
    }

    /** @return Collection<int, County> */
    public function counties(): Collection // returneaza toate judetele alfabetic
    {
        return County::orderBy('name')->get();
    }

    /** @return Collection<int, Locality> Localitatile mari apar primele. */
    public function localities(string $countyCode): Collection // localitatile unui judet
    {
        return Locality::query()
            ->where('county_code', $countyCode)
            ->orderBy('rang')
            ->orderBy('name')
            ->get();
    }

    /** Codul SIRUTA al unei localitati - devine address.cityCode in payload. */
    public function sirutaFor(string $countyCode, string $city): ?int // ai numele localitatii, vrei codul
    {
        return Locality::query()
            ->where('county_code', $countyCode)
            ->where('name', $city)
            ->value('siruta');
    }
}
