<?php

namespace Tests\Feature\Rca;

use App\Models\ApiLog;
use App\Services\Rca\RcaTokenManager;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRcaAuth;
use Tests\TestCase;

class RcaTokenManagerTest extends TestCase
{
    use FakesRcaAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();
        Http::preventStrayRequests();
    }

    public function test_se_autentifica_o_singura_data_si_refoloseste_tokenul(): void
    {
        $token = $this->jwt();

        Http::fake(['*/auth*' => Http::response($this->authResponse($token))]);

        $manager = app(RcaTokenManager::class);

        $this->assertSame($token, $manager->token());
        $this->assertSame($token, $manager->token());

        Http::assertSentCount(1);
        $this->assertSame(1, ApiLog::count());
    }

    public function test_expirarea_se_ia_din_jwt_nu_din_textul_expires_at(): void
    {
        // JWT-ul e deja expirat, dar 'expires_at' pretinde ca mai are 12 ore.
        // Daca ne-am lua dupa text, al doilea apel ar veni gresit din cache.
        $expirat = $this->jwt(secondsUntilExpiry: -10);
        $valid   = $this->jwt();

        Http::fake([
            '*/auth*' => Http::sequence()
                ->push($this->authResponse($expirat, \Illuminate\Support\Carbon::now()->addHours(12)->format('Y-m-d H:i:s')))
                ->push($this->authResponse($valid)),
        ]);

        $manager = app(RcaTokenManager::class);
        $manager->token();
        $manager->token();

        Http::assertSentCount(2);

        // Al doilea apel e reinnoirea cu refresh_token, nu o autentificare completa.
        Http::assertSent(fn ($request) => $request->method() === 'PATCH');
    }

    public function test_daca_reinnoirea_esueaza_se_face_autentificare_completa(): void
    {
        $expirat = $this->jwt(secondsUntilExpiry: -10);
        $nou     = $this->jwt();

        Http::fake([
            '*/auth*' => Http::sequence()
                ->push($this->authResponse($expirat))                       // POST initial
                ->push(['error' => true, 'message' => 'refresh invalid'], 401) // PATCH esuat
                ->push($this->authResponse($nou)),                          // POST de rezerva
        ]);

        $manager = app(RcaTokenManager::class);
        $manager->token();

        $this->assertSame($nou, $manager->token());
        Http::assertSentCount(3);
    }

    public function test_parola_si_tokenul_nu_ajung_in_api_logs(): void
    {
        Http::fake(['*/auth*' => Http::response($this->authResponse($this->jwt()))]);

        app(RcaTokenManager::class)->token();

        $log = ApiLog::sole();

        $this->assertStringContainsString('password=***', $log->url);
        $this->assertStringNotContainsString('parola-test', $log->url);
        $this->assertSame('***', $log->response_body['data']['token']);
        $this->assertSame('***', $log->response_body['data']['refresh_token']);
    }
}
