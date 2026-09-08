@extends('layouts.app')

@section('title', 'Creează cont')

@section('content')

    <div class="mx-auto max-w-md">
        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-xl font-semibold tracking-tight">Creează cont</h1>
            <p class="mt-1 text-sm text-slate-600">
                Îți salvezi datele o dată și nu le mai completezi la fiecare ofertă.
            </p>

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf

                <x-field name="name" label="Nume complet" required autofocus />
                <x-field name="email" label="Email" type="email" required />
                <x-field name="password" label="Parolă" type="password" required
                         hint="Minimum 8 caractere." />
                <x-field name="password_confirmation" label="Confirmă parola" type="password" required />

                <button type="submit"
                        class="w-full rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">
                    Creează contul
                </button>
            </form>
        </div>

        <p class="mt-4 text-center text-sm text-slate-600">
            Ai deja cont?
            <a href="{{ route('login') }}" class="font-medium text-sky-600 hover:underline">Autentifică-te</a>
        </p>
    </div>

@endsection
