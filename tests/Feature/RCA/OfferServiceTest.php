<?php

namespace Feature\RCA;

use App\Models\ApiLog;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\Locality;
use App\Models\Offer;
use App\Services\Rca\OfferService;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRcaAuth;
use Tests\TestCase;

class OfferServiceTest extends TestCase
{
    use FakesRcaAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();
        Http::preventStrayRequests();

        County::create(['code' => 'AR', 'name' => 'ARAD', 'siruta' => 26]);
        Locality::create(['county_code' => 'AR', 'name' => 'ARAD', 'rang' =>
            2, 'siruta' => 9271]);

        // Toate apelurile merg la acelasi URL (/offer), deci raspunsul se alege
          // dupa asiguratorul din corpul cererii.
          Http::fake(function ($request) {
              if (str_contains($request->url(), '/auth')) {
                  return Http::response($this->authResponse($this->jwt()));
              }

              $provider =
                  $request->data()['provider']['organization']['businessName'];

              if ($provider === 'dallbogg') {
                  return Http::response(['error' => true, 'status' => 400,
                      'message' => 'Produs indisponibil'], 400);
              }

              return Http::response([
                  'error'  => false,
                  'status' => 200,
                  'data'   => [
                      'provider' => ['organization' => ['businessName' =>
                          $provider]],
                      'offers'   => [[
                          'offerId'             => crc32($provider),
                          'providerOfferCode'   => 'COD-'.$provider,
                          'premiumAmount'       => 2211.49,
                          'premiumAmountNet'    => 1946.11,
                          'currency'            => 'RON',
                          'startDate'           => '2026-09-15',
                          'endDate'             => '2027-09-14',
                          'bonusMalusClass'     => 'B8',
                          'commissionPercent'   => 12,
                          'installments'        => [['id' => 1, 'amount' =>
                              2211.49, 'dueDate' => '2026-09-15']],
                          'greenCardExclusions' => 'RUS,BY',
                          'offerExpiryDate'     => '2026-09-05',
                      ]],
                  ],
              ]);
          });
      }

    public function
    test_interogheaza_toti_asiguratorii_si_salveaza_rezultatele(): void
    {
        $quoteRequest = app(OfferService::class)->quote($this->input());

        $this->assertSame('completed', $quoteRequest->status);
        $this->assertSame('AR08WSX', $quoteRequest->license_plate);
        $this->assertSame('Giosu Robert', $quoteRequest->policyholder_name);

        // Cate un rand per asigurator, indiferent daca a reusit sau nu.
        $this->assertCount(11, $quoteRequest->providerQuotes);
        $this->assertSame(10, $quoteRequest->offers()->count());
    }

    public function test_caderea_unui_asigurator_nu_afecteaza_restul(): void
    {
        $quoteRequest = app(OfferService::class)->quote($this->input());

        $dallbogg = $quoteRequest->providerQuotes()->where('provider',
            'dallbogg')->sole();

        $this->assertSame('error', $dallbogg->status);
        $this->assertSame(400, $dallbogg->http_status);
        $this->assertSame('Produs indisponibil', $dallbogg->error_message);
        $this->assertTrue($dallbogg->failureIsExpected());

        // Restul si-au vazut de treaba.
        $this->assertSame(10, $quoteRequest->providerQuotes()->where('status',
            'ok')->count());
    }

    public function test_datele_introduse_se_salveaza_inainte_de_apeluri():
    void
    {
        $quoteRequest = app(OfferService::class)->quote($this->input());

        // Formularul complet, exact cum a fost trimis.
        $this->assertSame('WVWZZZ1KZ8W006165',
            $quoteRequest->input['vehicle']['vin']);
        $this->assertSame('5050518020094',
            $quoteRequest->input['policyholder']['taxId']);

        $eveniment = AuditEvent::where('event', 'quote.requested')->sole();
        $this->assertSame($quoteRequest->id, $eveniment->subject_id);
        $this->assertSame($quoteRequest->correlation_id,
            $eveniment->correlation_id);
    }

    public function test_fiecare_apel_lasa_urma_in_api_logs_cu_asiguratorul_lui(): void
    {
        $quoteRequest = app(OfferService::class)->quote($this->input());

        $loguri = ApiLog::whereNotNull('provider')->get();

        $this->assertCount(11, $loguri);
        $this->assertSame($quoteRequest->id,
            $loguri->first()->quote_request_id);
        $this->assertSame('***', $loguri->first()->request_headers['Token']);

        // Payload-ul trimis ramane recuperabil pentru fiecare asigurator.
        $omniasig = $loguri->firstWhere('provider', 'omniasig');
        $this->assertSame('B0', $omniasig->request_body['product']['additionalData']['product']['bonusMalusPrevClass']);
    }

    public function test_oferta_se_salveaza_cu_toate_campurile(): void
    {
        app(OfferService::class)->quote($this->input());

        $offer = Offer::where('provider', 'axeria')->sole();

        $this->assertSame('2211.49', $offer->premium_amount);
        $this->assertSame('B8', $offer->bonus_malus_class);
        $this->assertSame('2026-09-15', $offer->start_date->toDateString());
        $this->assertSame(2211.49, $offer->installments[0]['amount']);
        $this->assertSame('COD-axeria', $offer->raw['providerOfferCode']);
    }

    private function input(): array
    {
        return OfferPayloadBuilderTest::datePentruTeste();
    }
}
