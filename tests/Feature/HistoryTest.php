<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\AuditEvent;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();

        $this->user = User::create([
            'name' => 'Robert Giosu',
            'email' => 'robert@example.com',
            'password' => 'parola-sigura-123',
        ]);
    }

    public function test_istoricul_cere_autentificare(): void
    {
        $this->get('/istoric')->assertRedirect('/autentificare');
    }

    public function test_arata_doar_cererile_proprii(): void
    {
        $aMea = $this->cerere($this->user->id, 'AR08WSX');

        $altul = User::create(['name' => 'Altcineva', 'email' => 'alt@example.com', 'password' => 'parola-sigura-123']);
        $aAltuia = $this->cerere($altul->id, 'CJ99XYZ');

        $raspuns = $this->actingAs($this->user)->get('/istoric');

        $raspuns->assertOk();
        $raspuns->assertSee('AR08WSX');
        $raspuns->assertDontSee('CJ99XYZ');
    }

    public function test_nu_pot_deschide_urma_altcuiva(): void
    {
        $altul = User::create(['name' => 'Altcineva', 'email' => 'alt@example.com', 'password' => 'parola-sigura-123']);
        $aAltuia = $this->cerere($altul->id, 'CJ99XYZ');

        $this->actingAs($this->user)
            ->get(route('istoric.show', $aAltuia))
            ->assertNotFound();
    }

    public function test_urma_arata_tot_ce_s_a_introdus(): void
    {
        $cerere = $this->cerere($this->user->id, 'AR08WSX');

        $raspuns = $this->actingAs($this->user)->get(route('istoric.show', $cerere));

        $raspuns->assertOk();

        // Fiecare camp completat apare, cu cheia si valoarea lui.
        $raspuns->assertSee('vehicle.licensePlate');
        $raspuns->assertSee('AR08WSX');
        $raspuns->assertSee('policyholder.taxId');
        $raspuns->assertSee('5050518020094');
        $raspuns->assertSee('policyholder.address.street');
        $raspuns->assertSee('Coriolan Petreanu');
    }

    public function test_urma_arata_asiguratorii_si_apelurile_http(): void
    {
        $cerere = $this->cerere($this->user->id, 'AR08WSX');

        $raspuns = $this->actingAs($this->user)->get(route('istoric.show', $cerere));

        // Asiguratorii, cu etichetele din config.
        $raspuns->assertSee('Omniasig');
        $raspuns->assertSee('DallBogg');
        $raspuns->assertSee('1842 ms');

        // Apelurile HTTP.
        $raspuns->assertSee('/offer');
        $raspuns->assertSee('Produs indisponibil');

        // Evenimentele de audit.
        $raspuns->assertSee('quote.requested');
    }

    public function test_secretele_raman_mascate_si_in_interfata(): void
    {
        $cerere = $this->cerere($this->user->id, 'AR08WSX');

        ApiLog::create([
            'correlation_id' => $cerere->correlation_id,
            'quote_request_id' => $cerere->id,
            'method' => 'POST',
            'url' => config('rca.base_url').'/auth?account=test&password=***',
            'request_headers' => ['Token' => '***'],
            'response_body' => ['data' => ['token' => '***']],
            'response_status' => 200,
            'duration_ms' => 500,
        ]);

        $raspuns = $this->actingAs($this->user)->get(route('istoric.show', $cerere));

        $raspuns->assertSee('password=***');
        $raspuns->assertDontSee('password=test');
    }

    public function test_urma_aduna_si_apelurile_din_alte_sesiuni(): void
    {
        $cerere = $this->cerere($this->user->id, 'AR08WSX');

        // Emiterea politei si descarcarea PDF-ului se intampla in alte request-uri
        // web, deci cu alt correlation_id, dar tot despre aceasta cerere.
        ApiLog::create([
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'quote_request_id' => $cerere->id,
            'provider' => 'groupama',
            'method' => 'POST',
            'url' => config('rca.base_url').'/policy',
            'response_status' => 200,
            'duration_ms' => 900,
        ]);

        $raspuns = $this->actingAs($this->user)->get(route('istoric.show', $cerere));

        $raspuns->assertSee('/policy');
    }

    /** O cerere completa, cu doi asiguratori si urma lor. */
    private function cerere(?int $userId, string $numar): QuoteRequest
    {
        $correlationId = (string) \Illuminate\Support\Str::uuid();

        $cerere = QuoteRequest::create([
            'correlation_id' => $correlationId,
            'user_id' => $userId,
            'status' => 'completed',
            'license_plate' => $numar,
            'policyholder_name' => 'Giosu Robert',
            'start_date' => '2026-09-23',
            'ip' => '127.0.0.1',
            'user_agent' => 'Symfony',
            'input' => [
                'motor' => ['startDate' => '2026-09-23', 'termTime' => 12],
                'policyholder' => [
                    'taxId' => '5050518020094',
                    'address' => ['street' => 'Coriolan Petreanu', 'county' => 'AR'],
                ],
                'vehicle' => ['licensePlate' => $numar, 'isNew' => false],
            ],
        ]);

        $ok = $cerere->providerQuotes()->create([
            'provider' => 'omniasig',
            'status' => 'ok',
            'http_status' => 200,
            'duration_ms' => 1842,
            'offers_count' => 1,
        ]);

        $ok->offers()->create([
            'provider' => 'omniasig',
            'api_offer_id' => 794310,
            'premium_amount' => 5121.53,
            'currency' => 'RON',
            'raw' => [],
        ]);

        $cerere->providerQuotes()->create([
            'provider' => 'dallbogg',
            'status' => 'error',
            'http_status' => 400,
            'duration_ms' => 513,
            'error_message' => 'Produs indisponibil',
        ]);

        ApiLog::create([
            'correlation_id' => $correlationId,
            'quote_request_id' => $cerere->id,
            'provider' => 'omniasig',
            'method' => 'POST',
            'url' => config('rca.base_url').'/offer',
            'request_headers' => ['Token' => '***'],
            'request_body' => ['provider' => ['organization' => ['businessName' => 'omniasig']]],
            'response_status' => 200,
            'duration_ms' => 1842,
        ]);

        AuditEvent::create([
            'correlation_id' => $correlationId,
            'user_id' => $userId,
            'event' => 'quote.requested',
            'subject_type' => QuoteRequest::class,
            'subject_id' => $cerere->id,
            'payload' => ['licensePlate' => $numar],
            'ip' => '127.0.0.1',
        ]);

        return $cerere;
    }
}
