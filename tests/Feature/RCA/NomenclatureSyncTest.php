<?php

namespace Tests\Feature\Rca;

use App\Models\County;
use App\Models\Locality;
use App\Services\Rca\NomenclatureService;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRcaAuth;
use Tests\TestCase;

class NomenclatureSyncTest extends TestCase
{
    use FakesRcaAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();
        Http::preventStrayRequests();

        Http::fake([
            '*/auth*' => Http::response($this->authResponse($this->jwt())),

            // Formatul REAL al API-ului, nu cel promis de specificatia OpenAPI.
            '*/nomenclature/county*' => Http::response([
                'error' => false, 'status' => 200,
                'data' => [
                    ['id' => 1, 'siruta' => 10, 'code' => 'AB', 'name' => 'ALBA', 'country' => 'RO'],
                    ['id' => 2, 'siruta' => 26, 'code' => 'AR', 'name' => 'ARAD', 'country' => 'RO'],
                ],
            ]),

            '*/nomenclature/locality/AB*' => Http::response([
                'error' => false, 'status' => 200,
                'data' => [
                    ['id' => 1, 'rang' => 5, 'name' => 'ABRUD-SAT', 'countyCode' => 'AB', 'siruta' => 1032],
                    ['id' => 2, 'rang' => 3, 'name' => 'ABRUD', 'countyCode' => 'AB', 'siruta' => 1028],
                ],
            ]),

            '*/nomenclature/locality/AR*' => Http::response([
                'error' => false, 'status' => 200,
                'data' => [
                    ['id' => 717, 'rang' => 2, 'name' => 'ARAD', 'countyCode' => 'AR', 'siruta' => 9271],
                    // Duplicat trimis intentionat: nu trebuie sa strice upsert-ul.
                    ['id' => 717, 'rang' => 2, 'name' => 'ARAD', 'countyCode' => 'AR', 'siruta' => 9271],
                ],
            ]),
        ]);
    }

    public function test_salveaza_folosind_numele_reale_de_campuri(): void
    {
        $rezultat = app(NomenclatureService::class)->sync();

        $this->assertSame(['counties' => 2, 'localities' => 3], $rezultat);

        $this->assertSame('ARAD', County::where('code', 'AR')->value('name'));
        $this->assertSame(9271, Locality::where('name', 'ARAD')->value('siruta'));
        $this->assertSame(2, Locality::where('name', 'ARAD')->value('rang'));
    }

    public function test_sincronizarea_repetata_nu_duplica_nimic(): void
    {
        $service = app(NomenclatureService::class);

        $service->sync();
        $service->sync();

        $this->assertSame(2, County::count());
        $this->assertSame(3, Locality::count());
    }

    public function test_localitatile_mari_apar_primele_in_dropdown(): void
    {
        $service = app(NomenclatureService::class);
        $service->sync();

        // ABRUD (rang 3) inaintea lui ABRUD-SAT (rang 5), desi alfabetic ar fi invers.
        $this->assertSame(['ABRUD', 'ABRUD-SAT'], $service->localities('AB')->pluck('name')->all());
    }
}
