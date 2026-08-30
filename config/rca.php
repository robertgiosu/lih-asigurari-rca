<?php

/*
   La fiecare pornire a aplicatiei, Laravel citeste toate fisierele din config/ si le pune intr-un singur array mare,
   folosind numele fisierului drept cheie. Deci array-ul returnat de rca.php devine adresabil cu punct:
   ce ai scris in fisier            cum il citesti oriunde in aplicatie
   'base_url' => '...'         →    config('rca.base_url')

    Fisierul config/rca.php e stratul de mijloc: ia secretele din .env si le expune la restul aplicatiei sub un pseudonume.
    .env                    config/rca.php                  codul tău
      ─────────               ──────────────                  ─────────
      RCA_ACCOUNT=test  ──→   'account' => env('RCA_ACCOUNT')  ──→  config('rca.account')
         (secret,                  (singurul loc unde                 (peste tot,
          nu in Git)                e voie sa apara env())            fara secrete)

    config:cache. In producție rulezi php artisan config:cache, care serializeaza tot config/ o data, la deploy. De
    aia env() are voie sa apara doar in config/ — in orice alta parte a codului, dupa cache, env() intoarce null.
    E capcana clasică în Laravel.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Conexiunea la API-ul Life is Hard
    |--------------------------------------------------------------------------
    */

    'base_url' => env('RCA_BASE_URL', 'https://rca-qa.api.lifeishard.ro'),

    'account'  => env('RCA_ACCOUNT'),
    'password' => env('RCA_PASSWORD'),

    'timeout'         => (int) env('RCA_TIMEOUT', 30),
    'connect_timeout' => (int) env('RCA_CONNECT_TIMEOUT', 10),

    // Trimis ca header Content-Language; face ca erorile de la API sa vina in romana.
    'language' => env('RCA_LANGUAGE', 'ro'),

    /*
    |--------------------------------------------------------------------------
    | Asiguratorii interogati
    |--------------------------------------------------------------------------
    |
    | 'extra' spune ce trebuie adaugat in product.additionalData pentru
    | asiguratorul respectiv. Valori posibile:
    |
    |   pti                 -> additionalData.product.vehicle.expirationDatePti
    |   bonus_malus_prev    -> additionalData.product.bonusMalusPrevClass
    |   bonus_malus_current -> additionalData.product.bonusMalusCurrentClass
    |   house_number        -> address.houseNumber devine obligatoriu
    |
    | 'expect_failure' marcheaza asiguratorii despre care stim ca nu raspund
    | in mediul QA, ca sa nu fie afisati ca o eroare reala.
    |
    */

    'providers' => [

        'allianz' => [
            'label' => 'Allianz-Țiriac',
            'extra' => [],
        ],

        'asirom' => [
            'label' => 'Asirom',
            'extra' => [],
        ],

        'axeria' => [
            'label' => 'Axeria',
            'extra' => ['house_number'],
        ],

        'eazy_insure' => [
            'label' => 'Eazy Insure',
            'extra' => [],
        ],

        'generali' => [
            'label' => 'Generali',
            'extra' => ['pti'],
        ],

        'grawe' => [
            'label' => 'Grawe',
            'extra' => ['pti', 'bonus_malus_prev', 'bonus_malus_current'],
        ],

        'groupama' => [
            'label' => 'Groupama',
            'extra' => ['pti'],
        ],

        'hellas_autonom' => [
            'label' => 'Hellas Direct (Autonom)',
            'extra' => ['pti'],
        ],

        'hellas_nextins' => [
            'label' => 'Hellas Direct (NextIns)',
            'extra' => ['pti'],
        ],

        'omniasig' => [
            'label' => 'Omniasig',
            'extra' => ['pti', 'bonus_malus_prev', 'bonus_malus_current'],
        ],

        'dallbogg' => [
            'label'          => 'DallBogg',
            'extra'          => [],
            'expect_failure' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Enumerarile din API
    |--------------------------------------------------------------------------
    |
    | Cheia = valoarea trimisa la API. Valoarea = eticheta afisata in interfata.
    | Folosite si la validare (array_keys), si la <select>-urile din formular.
    |
    */

    'enums' => [

        'registration_type' => [
            'registered'          => 'Înmatriculat',
            'recorded'            => 'Înregistrat',
            'temporaryRegistered' => 'Înmatriculat temporar',
            'temporaryRecorded'   => 'Înregistrat temporar',
        ],

        'vehicle_type' => [
            'M1'  => 'M1 — Autoturism',
            'M1G' => 'M1G — Autoturism de teren',
            'M2'  => 'M2 — Autobuz până în 5t',
            'M2G' => 'M2G — Autobuz de teren până în 5t',
            'M3'  => 'M3 — Autobuz peste 5t',
            'M3G' => 'M3G — Autobuz de teren peste 5t',
            'N1'  => 'N1 — Autoutilitară până în 3,5t',
            'N1G' => 'N1G — Autoutilitară de teren până în 3,5t',
            'N2'  => 'N2 — Camion 3,5–12t',
            'N2G' => 'N2G — Camion de teren 3,5–12t',
            'N3'  => 'N3 — Camion peste 12t',
            'N3G' => 'N3G — Camion de teren peste 12t',
            'O1'  => 'O1 — Remorcă până în 0,75t',
            'O2'  => 'O2 — Remorcă 0,75–3,5t',
            'O3'  => 'O3 — Remorcă 3,5–10t',
            'O4'  => 'O4 — Remorcă peste 10t',
            'L1e' => 'L1e — Moped 2 roți',
            'L2e' => 'L2e — Moped 3 roți',
            'L3e' => 'L3e — Motocicletă',
            'L4e' => 'L4e — Motocicletă cu ataș',
            'L5e' => 'L5e — Mototriciclu',
            'L6e' => 'L6e — Cvadriciclu ușor',
            'L7e' => 'L7e — Cvadriciclu',
            'T'   => 'T — Tractor pe roți',
            'C'   => 'C — Tractor pe șenile',
            'R'   => 'R — Remorcă agricolă',
            'S'   => 'S — Echipament tractat',
        ],

        'fuel_type' => [
            'diesel'   => 'Motorină',
            'petrol'   => 'Benzină',
            'electric' => 'Electric',
            'hybrid'   => 'Hibrid',
            'lpg'      => 'GPL',
        ],

        'usage_type' => [
            'personal'                => 'Personal',
            'passengerTransportation' => 'Transport persoane',
            'taxi'                    => 'Taxi',
            'carRental'               => 'Închirieri auto',
            'drivingSchool'           => 'Școală de șoferi',
            'security'                => 'Pază și protecție',
            'courier'                 => 'Curierat',
            'cargoTransportation'     => 'Transport marfă',
            'distribution'            => 'Distribuție',
        ],

        'id_type' => [
            'CI'       => 'Carte de identitate',
            'PASSPORT' => 'Pașaport',
        ],

        'gender' => [
            'm' => 'Masculin',
            'f' => 'Feminin',
        ],

        'installment_count' => [
            1  => 'O singură rată',
            2  => '2 rate',
            4  => '4 rate',
            12 => '12 rate',
        ],

        'bonus_malus' => [
            'B0' => 'B0', 'B1' => 'B1', 'B2' => 'B2', 'B3' => 'B3',
            'B4' => 'B4', 'B5' => 'B5', 'B6' => 'B6', 'B7' => 'B7',
            'B8' => 'B8',
            'M1' => 'M1', 'M2' => 'M2', 'M3' => 'M3', 'M4' => 'M4',
            'M5' => 'M5', 'M6' => 'M6', 'M7' => 'M7', 'M8' => 'M8',
        ],

        'payment_method' => [
            'receipt'              => 'Chitanță',
            'broker receipt'       => 'Chitanță broker',
            'payment order'        => 'Ordin de plată',
            'broker payment order' => 'Ordin de plată broker',
            'pos'                  => 'POS',
        ],

    ],

];
