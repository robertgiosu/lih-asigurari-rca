@extends('layouts.app')

@section('title', 'Urma cererii '.$quoteRequest->license_plate)

@section('content')

    @php
        // Aplatizam JSON-ul introdus in perechi cheie-valoare, ca sa se vada
        // absolut tot ce a completat utilizatorul, fara sa alegem noi ce aratam.
        $introdus = collect(Arr::dot($quoteRequest->input))
            ->reject(fn ($valoare) => $valoare === null || $valoare === '');

        $afiseaza = function ($valoare) {
            if (is_bool($valoare)) {
                return $valoare ? 'Da' : 'Nu';
            }

            return (string) $valoare;
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Urma cererii {{ $quoteRequest->license_plate }}
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $quoteRequest->created_at->format('d.m.Y H:i:s') }} &middot;
                {{ $quoteRequest->policyholder_name }} &middot;
                stare <span class="font-medium">{{ $quoteRequest->status }}</span>
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('oferta.show', $quoteRequest) }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Vezi ofertele
            </a>
            <a href="{{ route('istoric.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Înapoi la istoric
            </a>
        </div>
    </div>

    {{-- Cine, de unde, cu ce sesiune --}}
    <div class="mb-6 grid grid-cols-1 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-4">
        @foreach([
            'Utilizator'     => $quoteRequest->user?->name ?? 'vizitator',
            'Adresă IP'      => $quoteRequest->ip ?? '—',
            'Sesiune'        => Str::limit($quoteRequest->session_id ?? '—', 12),
            'Correlation ID' => Str::limit($quoteRequest->correlation_id, 13),
        ] as $eticheta => $valoare)
            <div class="bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $eticheta }}</p>
                <p class="mt-1 font-mono text-sm text-slate-900">{{ $valoare }}</p>
            </div>
        @endforeach
    </div>

    <p class="mb-6 text-xs text-slate-500">
        Browser: <span class="font-mono">{{ Str::limit($quoteRequest->user_agent ?? '—', 140) }}</span>
    </p>

    {{-- 1. Ce a introdus utilizatorul --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm"
             x-data="{ brut: false }">
        <header class="flex items-center justify-between border-b border-slate-100 p-6">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Ce s-a introdus în formular</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $introdus->count() }} câmpuri, salvate înainte de orice apel către asigurători.
                </p>
            </div>
            <button type="button" @click="brut = ! brut"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                <span x-show="! brut">Vezi JSON brut</span>
                <span x-show="brut" x-cloak>Vezi tabel</span>
            </button>
        </header>

        <div x-show="! brut">
            <dl class="grid grid-cols-1 gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($introdus as $cheie => $valoare)
                    <div class="bg-white px-4 py-3">
                        <dt class="font-mono text-xs text-slate-500">{{ $cheie }}</dt>
                        <dd class="mt-0.5 text-sm text-slate-900">{{ $afiseaza($valoare) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div x-show="brut" x-cloak class="p-6">
            <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($quoteRequest->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </section>

    {{-- 2. Ce a raspuns fiecare asigurator --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 p-6">
            <h2 class="text-base font-semibold text-slate-900">Asigurătorii interogați</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $quoteRequest->providerQuotes->count() }} apeluri în paralel &middot;
                cel mai lent a răspuns în {{ $quoteRequest->providerQuotes->max('duration_ms') }} ms
            </p>
        </header>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Asigurător</th>
                        <th class="px-4 py-3">Stare</th>
                        <th class="px-4 py-3 text-right">HTTP</th>
                        <th class="px-4 py-3 text-right">Durată</th>
                        <th class="px-4 py-3 text-right">Oferte</th>
                        <th class="px-4 py-3">Mesaj</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($quoteRequest->providerQuotes->sortBy('provider') as $providerQuote)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $providerQuote->providerLabel() }}</td>
                            <td class="px-4 py-3">
                                @if($providerQuote->succeeded())
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">ok</span>
                                @else
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">eroare</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $providerQuote->http_status ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $providerQuote->duration_ms }} ms</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $providerQuote->offers_count }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ Str::limit($providerQuote->error_message, 90) ?: '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 3. Traseul complet al apelurilor HTTP --}}
    <section class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 p-6">
            <h2 class="text-base font-semibold text-slate-900">Apeluri către API</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $logs->count() }} cereri HTTP. Tokenul și parolele apar mascate,
                iar fișierele mari sunt înlocuite cu un marcaj.
            </p>
        </header>

        <ul class="divide-y divide-slate-100">
            @foreach($logs as $log)
                <li x-data="{ deschis: false }">
                    <button type="button" @click="deschis = ! deschis"
                            class="flex w-full items-center gap-3 px-6 py-3 text-left hover:bg-slate-50">
                        <span class="w-14 shrink-0 font-mono text-xs font-semibold text-slate-500">{{ $log->method }}</span>

                        <span class="w-24 shrink-0 text-xs text-slate-600">{{ $log->provider ?? '—' }}</span>

                        <span class="min-w-0 flex-1 truncate font-mono text-xs text-slate-700">
                            {{ Str::after($log->url, config('rca.base_url')) }}
                        </span>

                        @if($log->error)
                            <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">eroare</span>
                        @else
                            <span @class([
                                'rounded px-2 py-0.5 text-xs font-medium tabular-nums',
                                'bg-emerald-100 text-emerald-800' => $log->response_status < 300,
                                'bg-rose-100 text-rose-800' => $log->response_status >= 300,
                            ])>{{ $log->response_status }}</span>
                        @endif

                        <span class="w-20 shrink-0 text-right text-xs tabular-nums text-slate-500">{{ $log->duration_ms }} ms</span>
                    </button>

                    <div x-show="deschis" x-cloak class="space-y-4 bg-slate-50 px-6 py-4">
                        <p class="font-mono text-xs break-all text-slate-500">{{ $log->url }}</p>

                        @if($log->error)
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-rose-600">Eroare</p>
                                <pre class="overflow-x-auto rounded bg-white p-3 text-xs text-rose-800">{{ $log->error }}</pre>
                            </div>
                        @endif

                        @foreach(['Antete trimise' => $log->request_headers, 'Cerere' => $log->request_body, 'Răspuns' => $log->response_body] as $eticheta => $continut)
                            @if($continut)
                                <div>
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $eticheta }}</p>
                                    <pre class="max-h-80 overflow-auto rounded bg-slate-900 p-3 text-xs leading-relaxed text-slate-100">{{ json_encode($continut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- 4. Actiunile utilizatorului --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 p-6">
            <h2 class="text-base font-semibold text-slate-900">Acțiuni înregistrate</h2>
        </header>

        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($events as $eveniment)
                <li class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-6 py-3">
                    <span class="w-36 shrink-0 font-mono text-xs text-slate-500">
                        {{ $eveniment->created_at->format('d.m.Y H:i:s') }}
                    </span>
                    <span class="font-medium text-slate-900">{{ $eveniment->event }}</span>
                    <span class="text-xs text-slate-500">
                        {{ $eveniment->user_id ? 'utilizator #'.$eveniment->user_id : 'vizitator' }} &middot;
                        {{ $eveniment->ip }}
                    </span>
                    @if($eveniment->payload)
                        <span class="w-full font-mono text-xs text-slate-500">
                            {{ Str::limit(json_encode($eveniment->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200) }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

@endsection
