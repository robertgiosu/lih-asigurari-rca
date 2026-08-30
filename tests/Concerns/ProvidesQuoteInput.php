<?php

namespace Tests\Concerns;

/**
 * Datele reale din cerinta (Giosu Robert / VW Golf AR08WSX), in forma pe care
 * o trimite formularul catre OfferPayloadBuilder si OfferService.
 *
 * Traieste intr-un trait, nu intr-o clasa de test: o clasa care extinde
 * TestCase nu poate fi instantiata cu `new` in PHPUnit 12.
 */
trait ProvidesQuoteInput
{
    protected function quoteInput(): array
    {
        return [
            'motor' => [
                'startDate' => '2026-09-15',
                'termTime' => 12,
                'installmentCount' => 1,
            ],
            'policyholder' => [
                'lastName' => 'Giosu',
                'firstName' => 'Robert',
                'taxId' => '5050518020094',
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
                    'county' => 'AR',
                    'city' => 'ARAD',
                    'street' => 'Coriolan Petreanu',
                    'houseNumber' => '38',
                    'postcode' => '310151',
                ],
                'hasDisability' => false,
                'isRetired' => false,
            ],
            'vehicle' => [
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
            'options' => [
                'driverIsPolicyholder' => true,
                'expirationDatePti' => '2026-08-26',
                'bonusMalusClass' => 'B0',
            ],
        ];
    }
}
