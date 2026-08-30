<?php

return [
    'required'         => 'Câmpul :attribute este obligatoriu.',
    'required_if'      => 'Câmpul :attribute este obligatoriu.',
    'required_with'    => 'Câmpul :attribute este obligatoriu când ai completat :values.',
    'string'           => 'Câmpul :attribute trebuie să fie text.',
    'integer'          => 'Câmpul :attribute trebuie să fie un număr întreg.',
    'boolean'          => 'Câmpul :attribute trebuie să fie da sau nu.',
    'email'            => 'Câmpul :attribute trebuie să fie o adresă de email validă.',
    'date_format'      => 'Câmpul :attribute trebuie să aibă formatul :format.',
    'before'           => 'Câmpul :attribute trebuie să fie o dată dinaintea zilei de :date.',
    'before_or_equal'  => 'Câmpul :attribute nu poate fi în viitor.',
    'after_or_equal'   => 'Câmpul :attribute nu poate fi în trecut.',
    'in'               => 'Valoarea aleasă pentru :attribute nu este validă.',
    'exists'           => 'Valoarea aleasă pentru :attribute nu există.',
    'regex'            => 'Formatul câmpului :attribute nu este valid.',
    'size'             => ['string' => 'Câmpul :attribute trebuie să aibă :size caractere.'],
    'min'              => [
        'string'  => 'Câmpul :attribute trebuie să aibă cel puțin :min caractere.',
        'numeric' => 'Câmpul :attribute trebuie să fie cel puțin :min.',
    ],
    'max'              => [
        'string'  => 'Câmpul :attribute nu poate depăși :max caractere.',
        'numeric' => 'Câmpul :attribute nu poate fi mai mare de :max.',
    ],
];
