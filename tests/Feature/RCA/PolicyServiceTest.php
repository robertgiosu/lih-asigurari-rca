<?php

namespace Tests\Feature\Rca;

use App\Models\AuditEvent;
use App\Models\Offer;
use App\Models\QuoteRequest;
use App\Services\Rca\PolicyService;
use App\Services\Rca\RcaException;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRcaAuth;
use Tests\TestCase;

class PolicyServiceTest extends TestCase
{
    use FakesRcaAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();
        Http::preventStrayRequests();
        $this->fakePolicy();
    }

    public function test_transforma_oferta_in_polita_si_salveaza_totul(): void
    {
        $offer = $this->offer();

        $policy = app(PolicyService::class)->issue($offer);

        $this->assertSame('RO/05/M3', $policy->series);
        $this->assertSame('123456789', $policy->number);
        $this->assertSame('5844.00', $policy->premium_amount);
        $this->assertSame('allianz', $policy->provider);
        $this->assertSame(55123, $policy->api_policy_id);
        $this->assertSame($offer->id, $policy->offer_id);
        $this->assertNotNull($policy->uuid);
    }

    public function test_completeaza_singur_obiectul_payment(): void
    {
        $offer = $this->offer();

        app(PolicyService::class)->issue($offer);

        $trimis = $this->policyRequestBody();

        $this->assertSame(794318, $trimis['offerId']);
        $this->assertFalse($trimis['includeDirectCompensation']);
        $this->assertSame('broker receipt', $trimis['payment']['method']);
        $this->assertSame('RON', $trimis['payment']['currency']);
        $this->assertSame(5844.0, $trimis['payment']['amount']);
        $this->assertSame(now()->toDateString(), $trimis['payment']['date']);
        $this->assertSame(sprintf('RCA-%06d', $offer->id), $trimis['payment']['documentNumber']);
    }

    public function test_cu_decontare_directa_trimite_suma_mai_mare(): void
    {
        app(PolicyService::class)->issue($this->offer(), includeDirectCompensation: true);

        $trimis = $this->policyRequestBody();

        $this->assertTrue($trimis['includeDirectCompensation']);
        $this->assertSame(6012.0, $trimis['payment']['amount']);
    }

    public function test_refuza_a_doua_emitere_pentru_aceeasi_oferta(): void
    {
        $offer = $this->offer();

        app(PolicyService::class)->issue($offer);

        $this->expectException(RcaException::class);

        app(PolicyService::class)->issue($offer->fresh());
    }

    public function test_inregistreaza_evenimentul_de_audit(): void
    {
        $policy = app(PolicyService::class)->issue($this->offer());

        $eveniment = AuditEvent::where('event', 'policy.issued')->sole();

        $this->assertSame($policy->id, $eveniment->subject_id);
        $this->assertSame('RO/05/M3', $eveniment->payload['series']);
        $this->assertSame($policy->correlation_id ?? $eveniment->correlation_id, $eveniment->correlation_id);
    }

    public function test_apelul_ramane_in_api_logs_legat_de_cerere(): void
    {
        $offer = $this->offer();

        app(PolicyService::class)->issue($offer);

        $log = \App\Models\ApiLog::where('url', 'like', '%/policy')->sole();

        $this->assertSame('allianz', $log->provider);
        $this->assertSame($offer->providerQuote->quote_request_id, $log->quote_request_id);
        $this->assertSame('***', $log->request_headers['Token']);
    }

    /** Corpul cererii POST /policy, asa cum a plecat spre API. */
    private function policyRequestBody(): array
    {
        return collect(Http::recorded())
            ->first(fn (array $pereche) => str_contains($pereche[0]->url(), '/policy'))[0]
            ->data();
    }

    private function fakePolicy(): void
    {
        Http::fake([
            '*/auth*' => Http::response($this->authResponse($this->jwt())),
            '*/policy*' => Http::response([
                'error'  => false,
                'status' => 200,
                'data'   => ['policies' => [[
                    'provider'      => ['organization' => ['businessName' => 'allianz']],
                    'policyId'      => 55123,
                    'series'        => 'RO/05/M3',
                    'number'        => 123456789,
                    'startDate'     => '2026-09-23',
                    'endDate'       => '2027-09-22',
                    'premiumAmount' => 5844,
                    'currency'      => 'RON',
                    'payment'       => ['method' => 'broker receipt', 'currency' => 'RON', 'amount' => 5844],
                    'installments'  => [['id' => 1, 'amount' => 5844, 'dueDate' => '2026-09-22']],
                ]]],
            ]),
        ]);
    }

    private function offer(): Offer
    {
        $quoteRequest = QuoteRequest::create([
            'correlation_id' => Correlation::id(),
            'input'          => ['vehicle' => ['licensePlate' => 'AR08WSX']],
            'license_plate'  => 'AR08WSX',
        ]);

        $providerQuote = $quoteRequest->providerQuotes()->create([
            'provider' => 'allianz',
            'status'   => 'ok',
        ]);

        return $providerQuote->offers()->create([
            'provider'            => 'allianz',
            'api_offer_id'        => 794318,
            'premium_amount'      => 5844,
            'currency'            => 'RON',
            'direct_compensation' => ['premiumAmount' => 6012],
            'raw'                 => [],
        ]);
    }
}
