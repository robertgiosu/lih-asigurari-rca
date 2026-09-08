@extends('layouts.app')

@section('title', 'Profilul meu')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Profilul meu</h1>
        <p class="mt-1 text-sm text-slate-600">
            Datele completate aici se vor pre-completa automat la fiecare cerere de ofertă.
            Poți salva și parțial.
        </p>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-medium">Verifică datele completate:</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->unique() as $mesaj)
                    <li>{{ $mesaj }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('profil.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-section title="Date personale">
            <x-field name="last_name" label="Nume" :value="$profile->last_name" />
            <x-field name="first_name" label="Prenume" :value="$profile->first_name" />
            <x-field name="tax_id" label="CNP" :value="$profile->tax_id" maxlength="13"
                     hint="Stocat criptat în baza de date." />

            <x-select name="gender" label="Sex" :value="$profile->gender"
                      :options="config('rca.enums.gender')" placeholder="Alege" />
            <x-field name="birthdate" label="Data nașterii" type="date"
                     :value="$profile->birthdate?->toDateString()" />
            <x-field name="mobile_number" label="Telefon" :value="$profile->mobile_number"
                     placeholder="07XXXXXXXX" />
        </x-section>

        <x-section title="Act de identitate și permis">
            <x-select name="id_type" label="Tip act" :value="$profile->id_type ?? 'CI'"
                      :options="config('rca.enums.id_type')" />
            <x-field name="id_number" label="Serie și număr" :value="$profile->id_number"
                     placeholder="ZR088130" hint="Stocat criptat." />
            <x-field name="id_issue_authority" label="Emis de" :value="$profile->id_issue_authority"
                     placeholder="SPCLEP Arad" />

            <x-field name="id_issue_date" label="Data eliberării" type="date"
                     :value="$profile->id_issue_date?->toDateString()" />
            <x-field name="driving_license_issue_date" label="Data obținerii permisului" type="date"
                     :value="$profile->driving_license_issue_date?->toDateString()" />
        </x-section>

        <x-section title="Adresă">
            <div x-data="adresa(@js(['county' => old('county', $profile->county), 'city' => old('city', $profile->city)]))"
                 class="contents">

                <div>
                    <label for="county" class="block text-sm font-medium text-slate-700">Județ</label>
                    <select id="county" name="county" x-model="county" @change="schimbaJudetul()"
                            @class([
                                'mt-1 block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm outline-none focus:ring-2',
                                'border-slate-300 focus:border-sky-500 focus:ring-sky-100' => ! $errors->has('county'),
                                'border-rose-400 bg-rose-50' => $errors->has('county'),
                            ])>
                        <option value="">Alege județul</option>
                        @foreach($counties as $county)
                            <option value="{{ $county->code }}"
                                    @selected(old('county', $profile->county) === $county->code)>{{ $county->displayName() }}</option>
                        @endforeach
                    </select>
                    @error('county')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="city" class="block text-sm font-medium text-slate-700">Localitate</label>
                    <select id="city" name="city" x-model="city" :disabled="! county || seIncarca"
                            @class([
                                'mt-1 block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-400',
                                'border-slate-300 focus:border-sky-500 focus:ring-sky-100' => ! $errors->has('city'),
                                'border-rose-400 bg-rose-50' => $errors->has('city'),
                            ])>
                        <option value="" x-text="seIncarca ? 'Se încarcă...' : (county ? 'Alege localitatea' : 'Alege întâi județul')"></option>
                        <template x-for="localitate in localitati" :key="localitate.siruta">
                            <option :value="localitate.name" x-text="localitate.display"></option>
                        </template>
                    </select>
                    @error('city')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <x-field name="street" label="Strada" :value="$profile->street" />
            <x-field name="house_number" label="Număr" :value="$profile->house_number" />
            <x-field name="floor" label="Etaj" :value="$profile->floor"
                     placeholder="0" hint="Obligatoriu la Grawe. Scrie 0 pentru parter." />

            <x-field name="building" label="Bloc" :value="$profile->building" />
            <x-field name="staircase" label="Scara" :value="$profile->staircase" />
            <x-field name="apartment" label="Apartament" :value="$profile->apartment" />

            <x-field name="postcode" label="Cod poștal" :value="$profile->postcode" />
        </x-section>

        <x-section title="Altele">
            <div class="flex flex-wrap items-center gap-6 sm:col-span-2 lg:col-span-3">
                <x-check name="has_disability" label="Persoană cu dizabilități"
                         :checked="$profile->has_disability" />
                <x-check name="is_retired" label="Pensionar" :checked="$profile->is_retired" />
            </div>
        </x-section>

        <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-600">
                CNP-ul și seria actului se salvează criptate.
            </p>
            <button type="submit"
                    class="rounded-lg bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                Salvează datele
            </button>
        </div>
    </form>

@endsection
