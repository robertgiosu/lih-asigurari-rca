<?php

namespace Tests\Feature\Rca;

use App\Models\County;
use App\Models\Locality;
use App\Services\Rca\OfferPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProvidesQuoteInput;
use Tests\TestCase;

class OfferPayloadBuilderTest extends TestCase
{
    use ProvidesQuoteInput, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        County::create(['code' => 'AR', 'name' => 'ARAD', 'siruta' => 26]);
        Locality::create(['county_code' => 'AR', 'name' => 'ARAD', 'rang' =>
            2, 'siruta' => 9271]);
    }

    public function test_reproduce_exact_payload_ul_allianz_din_documentatie(): void
    {
        $payload = app(OfferPayloadBuilder::class)->build('allianz',
            $this->quoteInput());

        $this->assertEquals([
            'provider' => [
                'organization' => ['businessName' => 'allianz'],
                'authentication' => ['account' => '', 'password' => '', 'code'
                => ''],
            ],
            'product' => [
                'motor' => [
                    'startDate' => '2026-09-15',
                    'termTime' => 12,
                    'installmentCount' => 1,
                ],
                'policyholder' => [
                    'lastName' => 'Giosu',
                    'firstName' => 'Robert',
                    'isForeignPerson' => false,
                    'taxId' => '5050518020094',
                    'nationality' => 'RO',
                    'citizenship' => 'RO',
                    'gender' => 'm',
                    'birthdate' => '2005-05-18',
                    'email' => 'robertgiosu@email.com',
                    'mobileNumber' => '0744444444',
                    'identification' => [
                        'idType' => 'CI',
                        'idNumber' => 'ZR088130',
                        'issueAuthority' => 'SPCLEP Arad',
                        'issueDate' => '2023-05-23',
                    ],
                    'drivingLicense' => ['issueDate' => '2023-10-13'],
                    'address' => [
                        'country' => 'RO',
                        'county' => 'AR',
                        'city' => 'ARAD',
                        'cityCode' => 9271,
                        'street' => 'Coriolan Petreanu',
                        'houseNumber' => '38',
                        'postcode' => '310151',
                    ],
                    'hasDisability' => false,
                    'isRetired' => false,
                ],
                'vehicle' => [
                    'driver' => [[
                        'lastName' => 'Giosu',
                        'firstName' => 'Robert',
                        'taxId' => '5050518020094',
                        'identification' => ['idNumber' => 'ZR088130'],
                        'mobileNumber' => '0744444444',
                    ]],
                    'licensePlate' => 'AR08WSX',
                    'registrationType' => 'registered',
                    'vin' => 'WVWZZZ1KZ8W006165',
                    'vehicleType' => 'M1',
                    'brand' => 'Volkswagen',
                    'model' => 'Golf',
                    'yearOfConstruction' => 2008,
                    'engineDisplacement' => 1896,
                    'enginePower' => 77,
                    'totalWeight' => 1920,
                    'seats' => 5,
                    'fuelType' => 'diesel',
                    'firstRegistration' => '2008-03-06',
                    'usageType' => 'personal',
                    'identification' => ['idNumber' => 'G205791'],
                    'currentMileage' => 226000,
                    'hasMobilityModifications' => false,
                    'isLeased' => false,
                    'isNew' => false,
                ],
            ],
        ], $payload);

        // Allianz nu primeste additionalData.
        $this->assertArrayNotHasKey('additionalData', $payload['product']);
    }

    public function test_fiecare_asigurator_primeste_additional_data_specific(): void
    {
        $builder = app(OfferPayloadBuilder::class);
        $input = $this->quoteInput();

        // Generali: doar data ITP.
        $this->assertSame(
            ['product' => ['vehicle' => ['expirationDatePti' =>
                '2026-08-26']]],
            $builder->build('generali', $input)['product']['additionalData'],
        );

        // Omniasig: data ITP plus ambele clase bonus-malus.
        $this->assertEquals(
            ['product' => [
                'vehicle' => ['expirationDatePti' =>
                    '2026-08-26'],
                'bonusMalusPrevClass' => 'B0',
                'bonusMalusCurrentClass' => 'B0',
            ]],
            $builder->build('omniasig', $input)['product']['additionalData'],
        );

        // Asirom: nimic.
        $this->assertArrayNotHasKey('additionalData',
            $builder->build('asirom', $input)['product']);
    }

    public function test_renew_policy_se_trimite_doar_daca_e_completata(): void
    {
        $builder = app(OfferPayloadBuilder::class);

        $fara = $builder->build('axeria', $this->quoteInput());
        $this->assertArrayNotHasKey('renewPolicy', $fara['product']['motor']);

        $input = $this->quoteInput();
        $input['motor']['renewPolicy'] = ['series' => 'RO/01/AA', 'number' =>
            '123456789'];

        $cu = $builder->build('axeria', $input);
        $this->assertSame(['series' => 'RO/01/AA', 'number' => 123456789],
            $cu['product']['motor']['renewPolicy']);
    }

    public function test_soferul_poate_fi_o_alta_persoana_decat_asiguratul(): void
    {
        $input = $this->quoteInput();
        $input['options']['driverIsPolicyholder'] = false;
        $input['driver'] = [
            'lastName' => 'Pop',
            'firstName' => 'Vasile',
            'taxId' => '1910716000000',
            'identification' => ['idNumber' => 'CJ123456'],
            'mobileNumber' => '0766999000',
        ];

        $sofer = app(OfferPayloadBuilder::class)->build('allianz',
            $input)['product']['vehicle']['driver'][0];

        $this->assertSame('Pop', $sofer['lastName']);
        $this->assertSame('0766999000', $sofer['mobileNumber']);
    }

    public function test_campurile_optionale_goale_nu_ajung_in_payload(): void
    {
        $input = $this->quoteInput();
        $input['policyholder']['address']['building'] = '';
        $input['policyholder']['address']['apartment'] = null;

        $adresa = app(OfferPayloadBuilder::class)->build('allianz',
            $input)['product']['policyholder']['address'];

        $this->assertArrayNotHasKey('building', $adresa);
        $this->assertArrayNotHasKey('apartment', $adresa);
    }
}
