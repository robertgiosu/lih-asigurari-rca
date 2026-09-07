@extends('layouts.app')

@section('title', 'Calculează prețul RCA')

@section('content')

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Calculează prețul RCA</h1>
            <p class="mt-1 text-sm text-slate-600">
                Completează datele o singură dată și primești oferte de la toți asigurătorii disponibili.
            </p>
        </div>

        @if(app()->environment('local'))
            <a href="{{ route('oferta.create', ['demo' => 1]) }}"
               class="shrink-0 rounded-lg border border-dashed border-amber-400 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-100">
                Completează cu date de test
            </a>
        @endif
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50
  p-4 text-sm text-rose-800">
            <p class="font-medium">Verifică datele completate:</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->unique() as $mesaj)
                    <li>{{ $mesaj }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('oferta.store') }}" class="space-y-6"
          x-data="formularOferta(@js(collect(config('rca.providers'))->pluck('label')->values()))"
          @submit="porneste($event)">
    @csrf

        <x-section title="Polița" description="Perioada pentru care
  vrei asigurarea.">
            <x-field name="motor.startDate" label="Data de început"
                     type="date" required
                     :value="now()->addDay()->toDateString()" />

            <x-select name="motor.termTime" label="Durata" required
                      :value="12"
                      :options="[12 => '12 luni', 6 => '6 luni', 3 =>
  '3 luni', 1 => 'O lună']" />

            <x-select name="motor.installmentCount" label="Număr de
  rate" required :value="1"
                      :options="config('rca.enums.installment_count')"
            />

            <x-select name="options.bonusMalusClass" label="Clasa
  bonus-malus" required :value="'B0'"
                      :options="config('rca.enums.bonus_malus')"
                      hint="B0 dacă nu ai istoric de daune." />

            <x-field name="motor.renewPolicy.series" label="Seria
  poliței anterioare"
                     hint="Opțional, pentru reînnoire." />

            <x-field name="motor.renewPolicy.number" label="Numărul
  poliței anterioare" />
        </x-section>

        <x-section title="Asigurat" description="Persoana pe numele
  căreia se emite polița.">
            <x-field name="policyholder.lastName" label="Nume" required
            />
            <x-field name="policyholder.firstName" label="Prenume"
                     required />
            <x-field name="policyholder.taxId" label="CNP" required
                     maxlength="13"

                     hint="Se verifică cifra de control." />

            <x-select name="policyholder.gender" label="Sex" required
                      :options="config('rca.enums.gender')"
                      placeholder="Alege" />
            <x-field name="policyholder.birthdate" label="Data
  nașterii" type="date" required />
            <x-field name="policyholder.mobileNumber" label="Telefon"
                     required
                     placeholder="07XXXXXXXX" />

            <x-field name="policyholder.email" label="Email"
                     type="email" required />

            <x-select name="policyholder.identification.idType"
                      label="Act de identitate" required
                      :value="'CI'"
                      :options="config('rca.enums.id_type')" />
            <x-field name="policyholder.identification.idNumber"
                     label="Serie și număr act" required
                     placeholder="ZR088130" />

            <x-field name="policyholder.identification.issueAuthority"
                     label="Emis de" required
                     placeholder="SPCLEP Arad" />
            <x-field name="policyholder.identification.issueDate"
                     label="Data eliberării" type="date" required />
            <x-field name="policyholder.drivingLicense.issueDate"
                     label="Data obținerii permisului" type="date" required />

            <div class="flex items-end gap-6 sm:col-span-2
  lg:col-span-3">
                <x-check name="policyholder.hasDisability"
                         label="Persoană cu dizabilități" />
                <x-check name="policyholder.isRetired"
                         label="Pensionar" />
            </div>
        </x-section>

        <x-section title="Adresa asiguratului">
            <div x-data="adresa(@js(['county' =>
  old('policyholder.address.county'), 'city' =>
  old('policyholder.address.city')]))"
                 class="contents">

                <div>
                    <label for="county" class="block text-sm
  font-medium text-slate-700">
                        Județ<span class="text-rose-500"> *</span>
                    </label>
                    <select id="county"
                            name="policyholder[address][county]" required
                            x-model="county" @change="schimbaJudetul()"
                        @class([
                            'mt-1 block w-full rounded-md border
bg-white px-3 py-2 text-sm shadow-sm outline-none focus:ring-2',
                            'border-slate-300 focus:border-sky-500
focus:ring-sky-100' => ! $errors->has('policyholder.address.county'),
                            'border-rose-400 bg-rose-50' =>
$errors->has('policyholder.address.county'),
                        ])>
                        <option value="">Alege județul</option>
                        @foreach($counties as $county)
                            <option value="{{ $county->code }}" @selected(old('policyholder.address.county') === $county->code)>{{ $county->displayName() }}</option>
                        @endforeach
                    </select>
                    @error('policyholder.address.county')
                    <p class="mt-1 text-xs text-rose-600">{{
  $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="city" class="block text-sm font-medium
  text-slate-700">
                        Localitate<span class="text-rose-500"> *</span>
                    </label>
                    <select id="city"
                            name="policyholder[address][city]" required
                            x-model="city" :disabled="! county ||
  seIncarca"
                        @class([
                            'mt-1 block w-full rounded-md border
bg-white px-3 py-2 text-sm shadow-sm outline-none focus:ring-2
disabled:bg-slate-100 disabled:text-slate-400',
                            'border-slate-300 focus:border-sky-500
focus:ring-sky-100' => ! $errors->has('policyholder.address.city'),
                            'border-rose-400 bg-rose-50' =>
$errors->has('policyholder.address.city'),
                        ])>
                        <option value="" x-text="seIncarca ? 'Se
  încarcă...' : (county ? 'Alege localitatea' : 'Alege întâi
  județul')"></option>
                        <template x-for="localitate in localitati"
                                  :key="localitate.siruta">
                            <option :value="localitate.name"
                                    x-text="localitate.display"></option>
                        </template>
                    </select>
                    @error('policyholder.address.city')
                    <p class="mt-1 text-xs text-rose-600">{{
  $message }}</p>
                    @enderror
                </div>
            </div>
            <x-field name="policyholder.address.street" label="Strada"
                     required />
            <x-field name="policyholder.address.houseNumber"
                     label="Număr" required
                     hint="Obligatoriu la Axeria." />
            <x-field name="policyholder.address.floor" label="Etaj"
                     required
                     placeholder="0" hint="Obligatoriu la Grawe. Scrie
  0 pentru parter." />

            <x-field name="policyholder.address.building" label="Bloc"
            />
            <x-field name="policyholder.address.staircase"
                     label="Scara" />
            <x-field name="policyholder.address.apartment"
                     label="Apartament" />
            <x-field name="policyholder.address.postcode" label="Cod
  poștal" />
        </x-section>

        <x-section title="Vehicul">
            <x-field name="vehicle.licensePlate" label="Număr de
  înmatriculare" required placeholder="AR08WSX" />
            <x-select name="vehicle.registrationType" label="Tip
  înmatriculare" required :value="'registered'"
                      :options="config('rca.enums.registration_type')"
            />
            <x-field name="vehicle.vin" label="Serie șasiu (VIN)"
                     required maxlength="17" />

            <x-field name="vehicle.brand" label="Marca" required
                     placeholder="Volkswagen" />
            <x-field name="vehicle.model" label="Model" required
                     placeholder="Golf" />
            <x-select name="vehicle.vehicleType" label="Categorie"
                      required :value="'M1'"
                      :options="config('rca.enums.vehicle_type')" />

            <x-field name="vehicle.yearOfConstruction" label="An
  fabricație" type="number" required />
            <x-field name="vehicle.firstRegistration" label="Prima
  înmatriculare" type="date" />
            <x-select name="vehicle.fuelType" label="Combustibil"
                      required
                      :options="config('rca.enums.fuel_type')"
                      placeholder="Alege" />

            <x-field name="vehicle.engineDisplacement"
                     label="Capacitate cilindrică (cm³)" type="number" required />
            <x-field name="vehicle.enginePower" label="Putere (kW)"
                     type="number" required />
            <x-field name="vehicle.totalWeight" label="Masă totală
  (kg)" type="number" required />

            <x-field name="vehicle.seats" label="Număr de locuri"
                     type="number" required />
            <x-field name="vehicle.currentMileage" label="Kilometraj"
                     type="number" required />
            <x-select name="vehicle.usageType" label="Utilizare"
                      required :value="'personal'"
                      :options="config('rca.enums.usage_type')" />
            <x-field name="vehicle.identification.idNumber"
                     label="Serie carte de identitate (CIV)" required
                     placeholder="G205791" />
            <x-field name="options.expirationDatePti" label="Expirare
  ITP" type="date" required
                     hint="Cerut de Generali, Omniasig, Groupama, Grawe
  și Hellas." />

            <div class="flex flex-wrap items-end gap-6 sm:col-span-2
  lg:col-span-3">
                <x-check name="vehicle.isNew" label="Vehicul nou" />
                <x-check name="vehicle.isLeased" label="În leasing" />
                <x-check name="vehicle.hasMobilityModifications"
                         label="Adaptat pentru persoane cu dizabilități" />
            </div>
        </x-section>

        <x-section title="Șofer">
            <div class="sm:col-span-2 lg:col-span-3" x-data="{ acelasi:
  true }">
                <x-check name="options.driverIsPolicyholder"
                         label="Șoferul este aceeași persoană cu asiguratul"
                         :checked="true" x-model="acelasi" />

                <div x-show="! acelasi" x-cloak class="mt-4 grid
  grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-field name="driver.lastName" label="Nume șofer"
                    />
                    <x-field name="driver.firstName" label="Prenume
  șofer" />
                    <x-field name="driver.taxId" label="CNP șofer"
                             maxlength="13" />
                    <x-field name="driver.identification.idNumber"
                             label="Serie și număr act" />
                    <x-field name="driver.mobileNumber" label="Telefon
  șofer" placeholder="07XXXXXXXX" />
                </div>
            </div>
        </x-section>
        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-600">
                Interogăm {{ count(config('rca.providers')) }} asigurători. Poate dura până la 30 de secunde.
            </p>
            <button type="submit" :disabled="seTrimite"
                    class="rounded-lg bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-300 disabled:cursor-not-allowed disabled:bg-slate-400">
                <span x-show="! seTrimite">Calculează prețul</span>
                <span x-show="seTrimite" x-cloak>Se calculează...</span>
            </button>
        </div>

        <div x-show="seTrimite" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl">

                <svg class="mx-auto h-10 w-10 animate-spin text-sky-600" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                </svg>

                <h2 class="mt-5 text-lg font-semibold text-slate-900">Căutăm cele mai bune prețuri</h2>

                <p class="mt-1 text-sm text-slate-500">
                    Interogăm <span x-text="asiguratori.length"></span> asigurători în paralel.
                </p>

                <ul class="mt-5 flex flex-wrap justify-center gap-1.5">
                    <template x-for="asigurator in asiguratori" :key="asigurator">
                        <li x-text="asigurator" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600"></li>
                    </template>
                </ul>

                <p class="mt-6 font-mono text-2xl tabular-nums text-slate-900">
                    <span x-text="secunde"></span><span class="text-base text-slate-400">s</span>
                </p>

                <p x-show="secunde > 20" x-cloak class="mt-2 text-xs text-slate-500">
                    Unii asigurători răspund mai greu. Nu închide pagina.
                </p>
            </div>
        </div>

    </form>

@endsection
