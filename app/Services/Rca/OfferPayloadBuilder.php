<?php

namespace App\Services\Rca;

/**
 * Traduce datele din formular in payload-ul cerut de POST /offer.
 *
 * Nu face niciun apel HTTP: primeste un array, intoarce un array.
 */
class OfferPayloadBuilder
{
    public function __construct(private readonly NomenclatureService $nomenclature)
    {
    }

    public function build(string $provider, array $input): array // construieste payload-ul folosind celelalte metode
    {
        $payload = [
            'provider' => [
                'organization' => ['businessName' => $provider],
                // In QA se folosesc credentialele implicite ale platformei.
                'authentication' => ['account' => '', 'password' => '', 'code' => ''],
            ],
            'product' => [
                'motor'        => $this->motor($input),
                'policyholder' => $this->policyholder($input),
                'vehicle'      => $this->vehicle($input),
            ],
        ];

        if ($additional = $this->additionalData($provider, $input)) {
            $payload['product']['additionalData'] = $additional;
        }

        return $payload;
    }

    private function motor(array $input): array // partea despre polita (de cand incepe, pe cate luni, in cate rate + polita anterioara daca e reinnoire)
    {
        $motor = [
            'startDate'        => $input['motor']['startDate'],
            'termTime'         => (int) $input['motor']['termTime'],
            'installmentCount' => (int) ($input['motor']['installmentCount'] ?? 1),
        ];

        // Trimis doar daca utilizatorul a completat efectiv polita anterioara.
        $series = $input['motor']['renewPolicy']['series'] ?? null;
        $number = $input['motor']['renewPolicy']['number'] ?? null;

        if (filled($series) && filled($number)) {
            $motor['renewPolicy'] = ['series' => $series, 'number' => (int) $number];
        }

        return $motor;
    }

    private function policyholder(array $input): array // datele personale
    {
        $p = $input['policyholder'];

        return [
            'lastName'        => $p['lastName'],
            'firstName'       => $p['firstName'],
            'isForeignPerson' => false,
            'taxId'           => $p['taxId'],
            'nationality'     => 'RO',
            'citizenship'     => 'RO',
            'gender'          => $p['gender'],
            'birthdate'       => $p['birthdate'],
            'email'           => $p['email'],
            'mobileNumber'    => $p['mobileNumber'],
            'identification'  => [
                'idType'         => $p['identification']['idType'],
                'idNumber'       => $p['identification']['idNumber'],
                'issueAuthority' => $p['identification']['issueAuthority'],
                'issueDate'      => $p['identification']['issueDate'],
            ],
            'drivingLicense'  => ['issueDate' => $p['drivingLicense']['issueDate']],
            'address'         => $this->address($p['address']),
            'hasDisability'   => (bool) ($p['hasDisability'] ?? false),
            'isRetired'       => (bool) ($p['isRetired'] ?? false),
        ];
    }

    private function address(array $address): array // adresa asiguratorului
    {
        $result = [
            'country'  => 'RO',
            'county'   => $address['county'],
            'city'     => $address['city'],
            // Utilizatorul alege o localitate; codul SIRUTA il completam noi.
            'cityCode' => $this->nomenclature->sirutaFor($address['county'], $address['city']),
            'street'   => $address['street'],
        ];

        // Optionale: le trimitem doar daca exista, ca sa nu punem null-uri in payload.
        foreach (['houseNumber', 'building', 'staircase', 'apartment', 'floor', 'postcode'] as $key) {
            if (filled($address[$key] ?? null)) {
                $result[$key] = $address[$key];
            }
        }

        return $result;
    }

    private function vehicle(array $input): array // datele masinii
    {
        $v = $input['vehicle'];

        return [
            'driver'                   => [$this->driver($input)],
            'licensePlate'             => $v['licensePlate'],
            'registrationType'         => $v['registrationType'],
            'vin'                      => $v['vin'],
            'vehicleType'              => $v['vehicleType'],
            'brand'                    => $v['brand'],
            'model'                    => $v['model'],
            'yearOfConstruction'       => (int) $v['yearOfConstruction'],
            'engineDisplacement'       => (int) $v['engineDisplacement'],
            'enginePower'              => (int) $v['enginePower'],
            'totalWeight'              => (int) $v['totalWeight'],
            'seats'                    => (int) $v['seats'],
            'fuelType'                 => $v['fuelType'],
            'firstRegistration'        => $v['firstRegistration'],
            'usageType'                => $v['usageType'],
            'identification'           => ['idNumber' => $v['identification']['idNumber']],
            'currentMileage'           => (int) $v['currentMileage'],
            'hasMobilityModifications' => (bool) ($v['hasMobilityModifications'] ?? false),
            'isLeased'                 => (bool) ($v['isLeased'] ?? false),
            'isNew'                    => (bool) ($v['isNew'] ?? false),
        ];
    }

    private function driver(array $input): array // cine conduce
    {
        $p = $input['policyholder'];

        $d = ($input['options']['driverIsPolicyholder'] ?? true)
            ? $p
            : $input['driver'];

        return [
            'lastName'       => $d['lastName'],
            'firstName'      => $d['firstName'],
            'taxId'          => $d['taxId'],
            'identification' => ['idNumber' => $d['identification']['idNumber']],
            'mobileNumber'   => $d['mobileNumber'] ?? $p['mobileNumber'],
        ];
    }

    /**
     * Campurile cerute doar de anumiti asiguratori, conform 'extra' din config/rca.php.
     */
    private function additionalData(string $provider, array $input): array // campuri aditionale pe care le cere un singur asigurator
    {
        $extra = config("rca.providers.{$provider}.extra", []);
        $product = [];

        if (in_array('pti', $extra, true) && filled($input['options']['expirationDatePti'] ?? null)) {
            $product['vehicle']['expirationDatePti'] = $input['options']['expirationDatePti'];
        }

        $bonusMalus = $input['options']['bonusMalusClass'] ?? 'B0';

        if (in_array('bonus_malus_prev', $extra, true)) {
            $product['bonusMalusPrevClass'] = $bonusMalus;
        }

        if (in_array('bonus_malus_current', $extra, true)) {
            $product['bonusMalusCurrentClass'] = $bonusMalus;
        }

        return $product ? ['product' => $product] : [];
    }
}
