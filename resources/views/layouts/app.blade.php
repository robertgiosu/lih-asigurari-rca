<!DOCTYPE html>
<html lang="ro" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Calculator RCA')</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
        <a href="{{ route('oferta.create') }}" class="text-lg font-semibold tracking-tight">
            Calculator <span class="text-sky-600">RCA</span>
        </a>

        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ route('oferta.create') }}" class="text-slate-600 hover:text-sky-600">Ofertă nouă</a>

            @auth
                <span class="text-slate-300">|</span>
                <a href="{{ route('profil.edit') }}" class="text-slate-600 hover:text-sky-600">Profilul meu</a>
                <span class="text-slate-500">{{ auth()->user()->name }}</span>

                {{-- Deconectarea e POST, nu link: un GET poate fi declansat de --}}
                {{-- orice imagine dintr-un email (<img src="site.ro/deconectare">). --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-600 hover:text-rose-600">Ieși din cont</button>
                </form>
            @else
                <span class="text-slate-300">|</span>
                <a href="{{ route('login') }}" class="text-slate-600 hover:text-sky-600">Autentificare</a>
                <a href="{{ route('register') }}"
                   class="rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white transition hover:bg-slate-700">
                    Creează cont
                </a>
            @endauth
        </nav>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-8">
    @if(session('mesaj'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            {{ session('mesaj') }}
        </div>
    @endif

    @yield('content')
</main>

</body>
</html>
