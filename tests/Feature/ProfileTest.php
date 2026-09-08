<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\County;
use App\Models\Locality;
use App\Models\Profile;
use App\Models\User;
use App\Support\Correlation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Correlation::reset();

        County::create(['code' => 'AR', 'name' => 'ARAD', 'siruta' => 26]);
        Locality::create(['county_code' => 'AR', 'name' => 'ARAD', 'rang' => 2, 'siruta' => 9271]);

        $this->user = User::create([
            'name' => 'Robert Giosu',
            'email' => 'robert@example.com',
            'password' => 'parola-sigura-123',
        ]);
    }

    public function test_profilul_cere_autentificare(): void
    {
        $this->get('/profil')->assertRedirect('/autentificare');
    }

    public function test_salveaza_datele_personale(): void
    {
        $this->actingAs($this->user)
            ->put('/profil', $this->date())
            ->assertRedirect(route('profil.edit'));

        $profile = $this->user->fresh()->profile;

        $this->assertSame('Giosu', $profile->last_name);
        $this->assertSame('5050518020094', $profile->tax_id);
        $this->assertSame('2005-05-18', $profile->birthdate->toDateString());
        $this->assertSame('AR', $profile->county);
        $this->assertSame('ARAD', $profile->city);
        $this->assertTrue($profile->is_retired);
        $this->assertFalse($profile->has_disability);
    }

    public function test_cnp_ul_si_seria_actului_sunt_criptate_in_baza_de_date(): void
    {
        $this->actingAs($this->user)->put('/profil', $this->date());

        $brut = DB::table('profiles')->where('user_id', $this->user->id)->first();

        // In coloana nu se citeste valoarea reala...
        $this->assertNotSame('5050518020094', $brut->tax_id);
        $this->assertNotSame('ZR088130', $brut->id_number);
        $this->assertStringNotContainsString('5050518020094', $brut->tax_id);

        // ...dar prin model se citeste corect.
        $this->assertSame('5050518020094', $this->user->fresh()->profile->tax_id);
        $this->assertSame('ZR088130', $this->user->fresh()->profile->id_number);
    }

    public function test_salvarea_repetata_actualizeaza_acelasi_profil(): void
    {
        $this->actingAs($this->user)->put('/profil', $this->date());
        $this->actingAs($this->user)->put('/profil', ['last_name' => 'Popescu'] + $this->date());

        $this->assertSame(1, Profile::count());
        $this->assertSame('Popescu', $this->user->fresh()->profile->last_name);
    }

    public function test_un_cnp_invalid_este_respins(): void
    {
        $this->actingAs($this->user)
            ->put('/profil', ['tax_id' => '5050518020095'] + $this->date())
            ->assertSessionHasErrors('tax_id');

        $this->assertNull($this->user->fresh()->profile);
    }

    public function test_localitatea_trebuie_sa_apartina_judetului(): void
    {
        County::create(['code' => 'CJ', 'name' => 'CLUJ', 'siruta' => 54]);

        $this->actingAs($this->user)
            ->put('/profil', ['county' => 'CJ'] + $this->date())
            ->assertSessionHasErrors('city');
    }

    public function test_profilul_poate_fi_salvat_partial(): void
    {
        $this->actingAs($this->user)
            ->put('/profil', ['last_name' => 'Giosu'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Giosu', $this->user->fresh()->profile->last_name);
        $this->assertNull($this->user->fresh()->profile->tax_id);
    }

    public function test_auditul_retine_ce_campuri_s_au_schimbat_dar_nu_valorile(): void
    {
        $this->actingAs($this->user)->put('/profil', $this->date());

        $eveniment = AuditEvent::where('event', 'profile.updated')->sole();

        $this->assertContains('tax_id', $eveniment->payload['campuri']);

        // Valorile sensibile nu ajung niciodata in jurnalul de audit.
        $this->assertStringNotContainsString('5050518020094', json_encode($eveniment->payload));
        $this->assertStringNotContainsString('ZR088130', json_encode($eveniment->payload));
    }

    public function test_formularul_de_oferta_se_precompleteaza_din_profil(): void
    {
        $this->actingAs($this->user)->put('/profil', $this->date());

        $raspuns = $this->actingAs($this->user)->get('/oferta');
        $raspuns->assertOk();

        // Componenta pune 'name' si 'value' pe randuri diferite; normalizam spatiile
        // ca verificarea sa fie despre continut, nu despre formatare.
        $html = preg_replace('/\s+/', ' ', $raspuns->getContent());

        $this->assertStringContainsString('name="policyholder[lastName]" value="Giosu"', $html);
        $this->assertStringContainsString('name="policyholder[taxId]" value="5050518020094"', $html);
        $this->assertStringContainsString('name="policyholder[address][street]" value="Coriolan Petreanu"', $html);
        $this->assertStringContainsString('name="policyholder[address][floor]" value="1"', $html);

        // Emailul contului devine emailul asiguratului.
        $this->assertStringContainsString('name="policyholder[email]" value="robert@example.com"', $html);
    }

    public function test_pentru_un_vizitator_formularul_ramane_gol(): void
    {
        $raspuns = $this->get('/oferta');

        $raspuns->assertOk();
        $raspuns->assertDontSee('value="Giosu"', false);
    }

    /** Datele reale din cerinta, pe numele coloanelor din profil. */
    private function date(): array
    {
        return [
            'last_name' => 'Giosu',
            'first_name' => 'Robert',
            'tax_id' => '5050518020094',
            'gender' => 'm',
            'birthdate' => '2005-05-18',
            'mobile_number' => '0744444444',
            'id_type' => 'CI',
            'id_number' => 'ZR088130',
            'id_issue_authority' => 'SPCLEP Arad',
            'id_issue_date' => '2023-05-23',
            'driving_license_issue_date' => '2023-10-13',
            'county' => 'AR',
            'city' => 'ARAD',
            'street' => 'Coriolan Petreanu',
            'house_number' => '38',
            'floor' => '1',
            'postcode' => '310151',
            'is_retired' => '1',
        ];
    }
}
