<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();
    }

    public function test_un_vizitator_isi_poate_crea_cont(): void
    {
        $raspuns = $this->post('/inregistrare', [
            'name' => 'Robert Giosu',
            'email' => 'robert@example.com',
            'password' => 'parola-sigura-123',
            'password_confirmation' => 'parola-sigura-123',
        ]);

        $raspuns->assertRedirect(route('oferta.create'));
        $this->assertAuthenticated();

        $user = User::sole();
        $this->assertSame('robert@example.com', $user->email);

        // Parola e stocata hashuita, niciodata in clar.
        $this->assertNotSame('parola-sigura-123', $user->password);
        $this->assertTrue(Hash::check('parola-sigura-123', $user->password));

        $this->assertSame(1, AuditEvent::where('event', 'user.registered')->count());
    }

    public function test_emailul_trebuie_sa_fie_unic(): void
    {
        User::create(['name' => 'X', 'email' => 'robert@example.com', 'password' => 'ceva12345']);

        $this->post('/inregistrare', [
            'name' => 'Altcineva',
            'email' => 'robert@example.com',
            'password' => 'parola-sigura-123',
            'password_confirmation' => 'parola-sigura-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::count());
    }

    public function test_parola_trebuie_confirmata_si_de_lungime_minima(): void
    {
        $this->post('/inregistrare', [
            'name' => 'Robert',
            'email' => 'robert@example.com',
            'password' => 'scurt',
            'password_confirmation' => 'altceva',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_autentificare_reusita_si_esuata(): void
    {
        User::create(['name' => 'Robert', 'email' => 'robert@example.com', 'password' => 'parola-sigura-123']);

        $this->post('/autentificare', ['email' => 'robert@example.com', 'password' => 'gresita'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, AuditEvent::where('event', 'user.login_failed')->count());

        $this->post('/autentificare', ['email' => 'robert@example.com', 'password' => 'parola-sigura-123'])
            ->assertRedirect(route('oferta.create'));

        $this->assertAuthenticated();
        $this->assertSame(1, AuditEvent::where('event', 'user.login')->count());
    }

    public function test_incercarea_esuata_nu_salveaza_parola(): void
    {
        $this->post('/autentificare', ['email' => 'robert@example.com', 'password' => 'secret-total']);

        $eveniment = AuditEvent::where('event', 'user.login_failed')->sole();

        $this->assertSame('robert@example.com', $eveniment->payload['email']);
        $this->assertStringNotContainsString('secret-total', json_encode($eveniment->payload));
        $this->assertNotNull($eveniment->ip);
    }

    public function test_deconectarea_invalideaza_sesiunea(): void
    {
        $user = User::create(['name' => 'Robert', 'email' => 'robert@example.com', 'password' => 'parola-sigura-123']);

        $this->actingAs($user)
            ->post('/deconectare')
            ->assertRedirect(route('oferta.create'));

        $this->assertGuest();
        $this->assertSame(1, AuditEvent::where('event', 'user.logout')->count());
    }

    public function test_un_utilizator_logat_nu_mai_vede_paginile_de_cont(): void
    {
        $user = User::create(['name' => 'Robert', 'email' => 'robert@example.com', 'password' => 'parola-sigura-123']);

        $this->actingAs($user)->get('/autentificare')->assertRedirect('/oferta');
        $this->actingAs($user)->get('/inregistrare')->assertRedirect('/oferta');
    }

    public function test_paginile_de_cont_se_deschid_pentru_vizitatori(): void
    {
        $this->get('/autentificare')->assertOk()->assertSee('Intră în cont');
        $this->get('/inregistrare')->assertOk()->assertSee('Creează contul');
    }

    public function test_parola_nu_este_reafisata_dupa_o_eroare(): void
    {
        $raspuns = $this->followingRedirects()->post('/inregistrare', [
            'name' => '',
            'email' => 'robert@example.com',
            'password' => 'parola-sigura-123',
            'password_confirmation' => 'parola-sigura-123',
        ]);

        $raspuns->assertDontSee('parola-sigura-123');
    }
}
