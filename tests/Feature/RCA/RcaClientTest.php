<?php

namespace Tests\Feature\Rca;

use App\Models\ApiLog;
use App\Services\Rca\RcaClient;
use App\Services\Rca\RcaException;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRcaAuth;
use Tests\TestCase;

class RcaClientTest extends TestCase
{
    use FakesRcaAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();
        Http::preventStrayRequests();
    }

    public function test_trimite_tokenul_in_header_si_desface_plicul_data(): void
    {
        $token = $this->jwt();

        Http::fake([
            '*/auth*' => Http::response($this->authResponse($token)),
            '*/nomenclature/county*' => Http::response([
                'error'  => false,
                'status' => 200,
                'data'   => [['code' => 'AR', 'name' => 'ARAD', 'siruta' => 26]],
            ]),
        ]);

        $data = app(RcaClient::class)->get('/nomenclature/county');

        // Am primit continutul lui 'data', nu plicul intreg.
        $this->assertSame('AR', $data[0]['code']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/nomenclature/county')
            && $request->header('Token') === [$token]
            && $request->header('Content-Language') === ['ro']);
    }

    public function test_arunca_RcaException_cu_context_cand_api_ul_raspunde_cu_eroare(): void
    {
        Http::fake([
            '*/auth*'  => Http::response($this->authResponse($this->jwt())),
            '*/offer*' => Http::response(['error' => true, 'status' => 400, 'message' => 'VIN invalid'], 400),
        ]);

        try {
            app(RcaClient::class)->post('/offer', ['ceva' => 1], provider: 'omniasig');
            $this->fail('Trebuia sa arunce RcaException.');
        } catch (RcaException $e) {
            $this->assertSame('VIN invalid', $e->getMessage());
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame('omniasig', $e->provider);
        }
    }

    public function test_reincearca_exact_o_data_dupa_401(): void
    {
        $vechi = $this->jwt();
        $nou   = $this->jwt(secondsUntilExpiry: 40000);

        Http::fake([
            '*/auth*' => Http::sequence()
                ->push($this->authResponse($vechi))
                ->push($this->authResponse($nou)),
            '*/offer*' => Http::sequence()
                ->push(['error' => true, 'message' => 'token invalid'], 401)
                ->push(['error' => false, 'status' => 200, 'data' => ['offers' => [['offerId' => 7]]]]),
        ]);

        $data = app(RcaClient::class)->post('/offer', ['ceva' => 1]);

        $this->assertSame(7, $data['offers'][0]['offerId']);

        // auth, offer(401), auth din nou, offer(200)
        Http::assertSentCount(4);

        $ultimulOffer = collect(Http::recorded())
            ->filter(fn ($pereche) => str_contains($pereche[0]->url(), '/offer'))
            ->last();

        $this->assertSame([$nou], $ultimulOffer[0]->header('Token'));
    }

    public function test_continutul_urias_nu_ajunge_in_api_logs(): void
    {
        Http::fake([
            '*/auth*' => Http::response($this->authResponse($this->jwt())),
            '*/policy/*' => Http::response([
                'error'  => false,
                'status' => 200,
                'data'   => ['files' => [[
                    'type'    => 'Policy',
                    'name'    => 'polita.pdf',
                    'content' => str_repeat('A', 50000), // PDF base64
                ]]],
            ]),
        ]);

        app(RcaClient::class)->get('/policy/123');

        $log = ApiLog::where('url', 'like', '%/policy/123')->sole();

        $this->assertStringContainsString('octeti omisi', $log->response_body['data']['files'][0]['content']);
        $this->assertSame('polita.pdf', $log->response_body['data']['files'][0]['name']);
    }
}
