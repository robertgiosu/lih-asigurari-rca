@extends('layouts.app')

@section('title', 'Autentificare')

@section('content')

    <div class="mx-auto max-w-md">
        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h1 class="text-xl font-semibold tracking-tight">Autentificare</h1>
            <p class="mt-1 text-sm text-slate-600">
                Intră în cont ca datele tale să se completeze singure.
            </p>

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf

                <x-field name="email" label="Email" type="email" required autofocus />
                <x-field name="password" label="Parolă" type="password" required />

                <x-check name="remember" label="Ține-mă minte" />

                <button type="submit"
                        class="w-full rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">
                    Intră în cont
                </button>
            </form>
        </div>

        <p class="mt-4 text-center text-sm text-slate-600">
            Nu ai cont?
            <a href="{{ route('register') }}" class="font-medium text-sky-600 hover:underline">Creează unul</a>
        </p>
    </div>

@endsection
