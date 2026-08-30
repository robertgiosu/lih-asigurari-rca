<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Oricine poate cere o oferta, cu sau fara cont.
        return true;
    }

    /**
     * Browserul nu trimite deloc checkbox-urile nebifate, deci le completam
     * explicit; tot aici normalizam valorile pe care asiguratorii le vor majuscule.
     */
    protected function prepareForValidation(): void // face normalizare a datelor (trim-uri, etc.)
    {
        $policyholder = $this->input('policyholder', []);
        $vehicle      = $this->input('vehicle', []);
        $options      = $this->input('options', []);

        foreach (['hasDisability', 'isRetired'] as $camp) {
            $policyholder[$camp] = $this->boolean("policyholder.$camp");
        }

        foreach (['hasMobilityModifications', 'isLeased', 'isNew'] as $camp) {
            $vehicle[$camp] = $this->boolean("vehicle.$camp");
        }

        $options['driverIsPolicyholder'] = $this->boolean('options.driverIsPolicyholder');

        if (isset($vehicle['vin'])) {
            $vehicle['vin'] = strtoupper(trim($vehicle['vin']));
        }

        if (isset($vehicle['licensePlate'])) {
            $vehicle['licensePlate'] = strtoupper(preg_replace('/\s+/', '',
                $vehicle['licensePlate']));
        }

        $this->merge(compact('policyholder', 'vehicle', 'options'));
    }

    public function rules(): array
    {
        $enum = fn (string $name) => Rule::in(array_keys(config("rca.enums.$name")));

        return [
            // ---------- Polita ----------
            'motor.startDate'          => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'motor.termTime'           => ['required', 'integer', 'min:1', 'max:12'],
            'motor.installmentCount'   => ['required', $enum('installment_count')],
            'motor.renewPolicy.series' => ['nullable', 'string', 'max:30',
                'required_with:motor.renewPolicy.number'],
            'motor.renewPolicy.number' => ['nullable', 'string', 'max:30',
                'required_with:motor.renewPolicy.series'],

            // ---------- Asigurat ----------
            'policyholder.lastName'     => ['required', 'string', 'max:60'],
            'policyholder.firstName'    => ['required', 'string', 'max:60'],
            'policyholder.taxId'        => ['required', new Cnp],
            'policyholder.gender'       => ['required', $enum('gender')],
            'policyholder.birthdate'    => ['required', 'date_format:Y-m-d', 'before:today'],
            'policyholder.email'        => ['required', 'email', 'max:120'],
            'policyholder.mobileNumber' => ['required', 'regex:/^07\d{8}$/'],
            'policyholder.hasDisability' => ['boolean'],
            'policyholder.isRetired'     => ['boolean'],

            'policyholder.identification.idType'         => ['required', $enum('id_type')],
            'policyholder.identification.idNumber'       => ['required', 'string', 'max:20'],
            'policyholder.identification.issueAuthority' => ['required', 'string', 'max:80'],
            'policyholder.identification.issueDate'      => ['required', 'date_format:Y-m-d',
                'before_or_equal:today'],

            'policyholder.drivingLicense.issueDate' => ['required', 'date_format:Y-m-d',
                'before_or_equal:today'],

            'policyholder.address.county'      => ['required', 'string', 'size:2',
                Rule::exists('counties', 'code')],
            'policyholder.address.city'        => ['required', 'string', 'max:100'],
            'policyholder.address.street'      => ['required', 'string', 'max:100'],
            // Obligatoriu la Axeria.
            'policyholder.address.houseNumber' => ['required', 'string', 'max:20'],
            // Obligatoriu la Grawe - verificat pe API-ul real.
            'policyholder.address.floor'       => ['required', 'string', 'max:10'],
            'policyholder.address.building'    => ['nullable', 'string', 'max:20'],
            'policyholder.address.staircase'   => ['nullable', 'string', 'max:20'],
            'policyholder.address.apartment'   => ['nullable', 'string', 'max:20'],
            'policyholder.address.postcode'    => ['nullable', 'string', 'max:10'],

            // ---------- Vehicul ----------
            'vehicle.licensePlate'       => ['required', 'string', 'max:15'],
            'vehicle.registrationType'   => ['required', $enum('registration_type')],
            // Standardul VIN exclude literele I, O si Q.
            'vehicle.vin'                => ['required', 'string', 'min:5', 'max:17',
                'regex:/^[A-HJ-NPR-Z0-9]+$/'],
            'vehicle.vehicleType'        => ['required', $enum('vehicle_type')],
            'vehicle.brand'              => ['required', 'string', 'max:50'],
            'vehicle.model'              => ['required', 'string', 'max:50'],
            'vehicle.yearOfConstruction' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'vehicle.engineDisplacement' => ['required', 'integer', 'min:0', 'max:30000'],
            'vehicle.enginePower'        => ['required', 'integer', 'min:1', 'max:2000'],
            'vehicle.totalWeight'        => ['required', 'integer', 'min:1', 'max:60000'],
            'vehicle.seats'              => ['required', 'integer', 'min:1', 'max:100'],
            'vehicle.fuelType'           => ['required', $enum('fuel_type')],
            'vehicle.firstRegistration'  => ['nullable', 'date_format:Y-m-d',
                'before_or_equal:today'],
            'vehicle.usageType'          => ['required', $enum('usage_type')],
            'vehicle.identification.idNumber' => ['required', 'string', 'max:20'],
            'vehicle.currentMileage'     => ['required', 'integer', 'min:0', 'max:2000000'],
            'vehicle.hasMobilityModifications' => ['boolean'],
            'vehicle.isLeased'                 => ['boolean'],
            'vehicle.isNew'                    => ['boolean'],

            // ---------- Sofer, daca difera de asigurat ----------
            'options.driverIsPolicyholder'  => ['boolean'],
            'driver.lastName'               => ['required_if:options.driverIsPolicyholder,false',
                'nullable', 'string', 'max:60'],
            'driver.firstName'              => ['required_if:options.driverIsPolicyholder,false',
                'nullable', 'string', 'max:60'],
            'driver.taxId'                  => ['required_if:options.driverIsPolicyholder,false',
                'nullable', new Cnp],
            'driver.identification.idNumber' => ['required_if:options.driverIsPolicyholder,false',
                'nullable', 'string', 'max:20'],
            'driver.mobileNumber'           => ['nullable', 'regex:/^07\d{8}$/'],

            // ---------- Cerute de anumiti asiguratori ----------
            'options.expirationDatePti' => ['required', 'date_format:Y-m-d'],
            'options.bonusMalusClass'   => ['required', $enum('bonus_malus')],
        ];
    }

    /**
     * Verificari care privesc mai multe campuri deodata.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->checkLocalityBelongsToCounty($validator);
                $this->checkCnpMatchesPerson($validator);
            },
        ];
    }

    private function checkLocalityBelongsToCounty(Validator $validator): void
    {
        $county = $this->input('policyholder.address.county');
        $city   = $this->input('policyholder.address.city');

        if (! $county || ! $city) {
            return;
        }

        $exists = Locality::where('county_code', $county)->where('name', $city)->exists();

        if (! $exists) {
            $validator->errors()->add(
                'policyholder.address.city',
                'Localitatea nu apartine judetului selectat.',
            );
        }
    }

    /** Sexul si data nasterii sunt codificate in CNP: verificam ca se potrivesc. */
    private function checkCnpMatchesPerson(Validator $validator): void
    {
        $cnp = (string) $this->input('policyholder.taxId');

        if (! Cnp::isValid($cnp)) {
            return; // Regula Cnp a raportat deja problema.
        }

        if (Cnp::gender($cnp) !== null && Cnp::gender($cnp) !== $this->input('policyholder.gender'))
        {
            $validator->errors()->add('policyholder.gender', 'Sexul nu corespunde cu CNP-ul
  introdus.');
        }

        if (Cnp::birthdate($cnp) !== $this->input('policyholder.birthdate')) {
            $validator->errors()->add('policyholder.birthdate', 'Data nasterii nu corespunde cu
  CNP-ul introdus.');
        }
    }

    // Traduce numele tehnice ale câmpurilor în ceva citibil.
    // Fără ea, Laravel afișează:
    // The policyholder.identification.id number field is required.
    // Cu ea:
    // Câmpul seria și numărul actului este obligatoriu.
    /** Numele campurilor asa cum le vede utilizatorul in mesajele de eroare. */
    public function attributes(): array
    {
        return [
            'motor.startDate'                            => 'data de început',
            'motor.termTime'                             => 'durata poliței',
            'motor.installmentCount'                     => 'numărul de rate',
            'motor.renewPolicy.series'                   => 'seria poliței anterioare',
            'motor.renewPolicy.number'                   => 'numărul poliței anterioare',
            'policyholder.lastName'                      => 'numele asiguratului',
            'policyholder.firstName'                     => 'prenumele asiguratului',
            'policyholder.taxId'                         => 'CNP-ul asiguratului',
            'policyholder.gender'                        => 'sexul',
            'policyholder.birthdate'                     => 'data nașterii',
            'policyholder.email'                         => 'adresa de email',
            'policyholder.mobileNumber'                  => 'numărul de telefon',
            'policyholder.identification.idType'         => 'tipul actului de identitate',
            'policyholder.identification.idNumber'       => 'seria și numărul actului',
            'policyholder.identification.issueAuthority' => 'autoritatea emitentă',
            'policyholder.identification.issueDate'      => 'data eliberării actului',
            'policyholder.drivingLicense.issueDate'      => 'data obținerii permisului',
            'policyholder.address.county'                => 'județul',
            'policyholder.address.city'                  => 'localitatea',
            'policyholder.address.street'                => 'strada',
            'policyholder.address.houseNumber'           => 'numărul',
            'policyholder.address.floor'                 => 'etajul',
            'policyholder.address.postcode'              => 'codul poștal',
            'vehicle.licensePlate'                       => 'numărul de înmatriculare',
            'vehicle.registrationType'                   => 'tipul înmatriculării',
            'vehicle.vin'                                => 'seria de șasiu (VIN)',
            'vehicle.vehicleType'                        => 'categoria vehiculului',
            'vehicle.brand'                              => 'marca',
            'vehicle.model'                              => 'modelul',
            'vehicle.yearOfConstruction'                 => 'anul fabricației',
            'vehicle.engineDisplacement'                 => 'capacitatea cilindrică',
            'vehicle.enginePower'                        => 'puterea motorului',
            'vehicle.totalWeight'                        => 'masa totală',
            'vehicle.seats'                              => 'numărul de locuri',
            'vehicle.fuelType'                           => 'tipul de combustibil',
            'vehicle.firstRegistration'                  => 'data primei înmatriculări',
            'vehicle.usageType'                          => 'modul de utilizare',
            'vehicle.identification.idNumber'            => 'seria cărții de identitate a
  vehiculului',
            'vehicle.currentMileage'                     => 'kilometrajul',
            'options.expirationDatePti'                  => 'data expirării ITP',
            'options.bonusMalusClass'                    => 'clasa bonus-malus',
            'driver.lastName'                            => 'numele șoferului',
            'driver.firstName'                           => 'prenumele șoferului',
            'driver.taxId'                               => 'CNP-ul șoferului',
            'driver.identification.idNumber'             => 'actul de identitate al șoferului',
        ];
    }

    public function messages(): array
    {
        return [
            'policyholder.mobileNumber.regex' => 'Numărul de telefon trebuie să fie de forma
  07XXXXXXXX.',
            'driver.mobileNumber.regex'       => 'Numărul de telefon al șoferului trebuie să fie de
  forma 07XXXXXXXX.',
            'vehicle.vin.regex'               => 'Seria de șasiu poate conține doar cifre și litere,
  fără I, O sau Q.',
        ];
    }
}
