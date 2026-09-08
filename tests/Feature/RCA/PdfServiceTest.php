<?php

namespace Tests\Feature\Rca;

use App\Models\ApiLog;
use App\Models\AuditEvent;
use App\Models\Offer;
use App\Models\QuoteRequest;
use App\Services\Rca\PdfService;
use App\Services\Rca\RcaException;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FakesRcaAuth;
use Tests\TestCase;

class PdfServiceTest extends TestCase
{
    use FakesRcaAuth, RefreshDatabase;

    /** Un PDF minimal, valid. */
    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    public function test_decodeaza_base64_ul_si_scrie_pdf_ul_pe_disc(): void
    {
        $this->fakePdf();
        $offer = $this->offer();

        $path = app(PdfService::class)->forOffer($offer);

        $this->assertSame('rca/oferte/oferta-794318.pdf', $path);
        Storage::assertExists($path);

        // Continutul de pe disc e PDF-ul decodat, nu textul base64.
        $this->assertSame(self::PDF, Storage::get($path));
        $this->assertStringStartsWith('%PDF-', Storage::get($path));

        $this->assertSame($path, $offer->fresh()->pdf_path);
    }

    public function test_al_doilea_apel_foloseste_fisierul_deja_descarcat(): void
    {
        $this->fakePdf();
        $offer = $this->offer();

        $service = app(PdfService::class);
        $service->forOffer($offer);
        $service->forOffer($offer->fresh());

        // auth + un singur GET /offer/794318
        Http::assertSentCount(2);
    }

    public function test_arunca_eroare_daca_nu_vine_niciun_fisier(): void
    {
        Http::fake([
            '*/auth*'  => Http::response($this->authResponse($this->jwt())),
            '*/offer/*' => Http::response(['error' => false, 'status' => 200, 'data' => ['files' => []]]),
        ]);

        $this->expectException(RcaException::class);

        app(PdfService::class)->forOffer($this->offer());
    }

    public function test_continutul_base64_nu_ajunge_in_api_logs(): void
    {
        // Un fisier destul de mare cat sa depaseasca pragul de 2000 de caractere.
        $mare = base64_encode(str_repeat('A', 5000));

        Http::fake([
            '*/auth*'   => Http::response($this->authResponse($this->jwt())),
            '*/offer/*' => Http::response([
                'error' => false, 'status' => 200,
                'data'  => ['files' => [['type' => 'Offer', 'name' => 'o.pdf', 'content' => $mare]]],
            ]),
        ]);

        app(PdfService::class)->forOffer($this->offer());

        $log = ApiLog::where('url', 'like', '%/offer/794318')->sole();

        $this->assertStringContainsString('octeti omisi', $log->response_body['data']['files'][0]['content']);
        $this->assertSame('allianz', $log->provider);
    }

    public function test_inregistreaza_descarcarea_in_audit(): void
    {
        $this->fakePdf();

        app(PdfService::class)->forOffer($this->offer());

        $eveniment = AuditEvent::where('event', 'pdf.downloaded')->sole();

        $this->assertSame('oferta', $eveniment->payload['tip']);
        $this->assertSame('allianz', $eveniment->payload['provider']);
    }

    private function fakePdf(): void
    {
        Http::fake([
            '*/auth*'   => Http::response($this->authResponse($this->jwt())),
            '*/offer/*' => Http::response([
                'error'  => false,
                'status' => 200,
                'data'   => ['files' => [[
                    'type'    => 'Offer',
                    'name'    => 'offer-2149be26.pdf',
                    'content' => base64_encode(self::PDF),
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
            'provider'       => 'allianz',
            'api_offer_id'   => 794318,
            'premium_amount' => 5844,
            'currency'       => 'RON',
            'raw'            => [],
        ]);
    }
}
