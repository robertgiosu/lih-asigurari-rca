<?php

namespace App\Http\Requests;

use App\Models\Locality;
use App\Rules\Cnp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_disability' => $this->boolean('has_disability'),
            'is_retired'     => $this->boolean('is_retired'),
        ]);
    }

    public function rules(): array
    {
        $enum = fn (string $name) =>
        Rule::in(array_keys(config("rca.enums.$name")));

        // Totul e optional: profilul poate fi completat pe bucati.
        // Validarea stricta ramane in StoreQuoteRequest, la cererea de oferta.
          return [
        'last_name'                  => ['nullable', 'string', 'max:60'],
        'first_name'                 => ['nullable', 'string', 'max:60'],
        'tax_id'                     => ['nullable', new Cnp],
        'gender'                     => ['nullable', $enum('gender')],
        'birthdate'                  => ['nullable', 'date_format:Y-m-d',
            'before:today'],
        'mobile_number'              => ['nullable',
            'regex:/^07\d{8}$/'],

        'id_type'                    => ['nullable', $enum('id_type')],
        'id_number'                  => ['nullable', 'string', 'max:20'],
        'id_issue_authority'         => ['nullable', 'string', 'max:80'],
        'id_issue_date'              => ['nullable', 'date_format:Y-m-d',
            'before_or_equal:today'],
        'driving_license_issue_date' => ['nullable', 'date_format:Y-m-d',
            'before_or_equal:today'],

        'county'                     => ['nullable', 'string', 'size:2',
            Rule::exists('counties', 'code')],
        'city'                       => ['nullable', 'string',
            'max:100'],
        'street'                     => ['nullable', 'string',
            'max:100'],
              'house_number'               => ['nullable', 'string', 'max:20'],
              'building'                   => ['nullable', 'string', 'max:20'],
              'staircase'                  => ['nullable', 'string', 'max:20'],
              'apartment'                  => ['nullable', 'string', 'max:20'],
              'floor'                      => ['nullable', 'string', 'max:10'],
              'postcode'                   => ['nullable', 'string', 'max:10'],

              'has_disability'             => ['boolean'],
              'is_retired'                 => ['boolean'],
          ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $county = $this->input('county');
                $city   = $this->input('city');

                if ($county && $city && ! Locality::where('county_code',
                        $county)->where('name', $city)->exists()) {
                    $validator->errors()->add('city', 'Localitatea nu
  apartine judetului selectat.');
                }

                $cnp = (string) $this->input('tax_id');

                if (! $cnp || ! Cnp::isValid($cnp)) {
                    return;
                }

                if ($this->filled('gender') && Cnp::gender($cnp) !== null &&
                    Cnp::gender($cnp) !== $this->input('gender')) {
                    $validator->errors()->add('gender', 'Sexul nu corespunde
  cu CNP-ul introdus.');
                }

                if ($this->filled('birthdate') && Cnp::birthdate($cnp) !==
                    $this->input('birthdate')) {
                    $validator->errors()->add('birthdate', 'Data nasterii nu
  corespunde cu CNP-ul introdus.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'last_name'                  => 'numele',
            'first_name'                 => 'prenumele',
            'tax_id'                     => 'CNP-ul',
            'gender'                     => 'sexul',
            'birthdate'                  => 'data nașterii',
            'mobile_number'              => 'numărul de telefon',
            'id_type'                    => 'tipul actului de identitate',
            'id_number'                  => 'seria și numărul actului',
            'id_issue_authority'         => 'autoritatea emitentă',
            'id_issue_date'              => 'data eliberării actului',
            'driving_license_issue_date' => 'data obținerii permisului',
            'county'                     => 'județul',
            'city'                       => 'localitatea',
            'street'                     => 'strada',
            'house_number'               => 'numărul',
            'floor'                      => 'etajul',
            'postcode'                   => 'codul poștal',
        ];
    }

    public function messages(): array
    {
        return [
            'mobile_number.regex' => 'Numărul de telefon trebuie să fie de
  forma 07XXXXXXXX.',
        ];
    }
}
