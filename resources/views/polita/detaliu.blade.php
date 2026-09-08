@extends('layouts.app')

@section('title', 'Polița '.$policy->series.'/'.$policy->number)

@section('content')

    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        Polița a fost emisă la {{ $policy->providerLabel() }}.
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-6">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $policy->providerLabel() }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                {{ $policy->series }} {{ $policy->number }}
            </h1>
        </div>

        <dl class="grid grid-cols-1 gap-px bg-slate-100 sm:grid-cols-3">
            @foreach([
                'Primă'        => number_format((float) $policy->premium_amount, 2, ',', '.').' '.$policy->currency,
                'Valabilitate' => $policy->start_date?->format('d.m.Y').' – '.$policy->end_date?->format('d.m.Y'),
                'Asigurat'     => $policy->offer->providerQuote->quoteRequest->policyholder_name,
                'Vehicul'      => $policy->offer->providerQuote->quoteRequest->license_plate,
                'Emisă la'     => $policy->created_at->format('d.m.Y H:i'),
                'Cod intern'   => $policy->api_policy_id,
            ] as $eticheta => $valoare)
                <div class="bg-white p-6">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">{{ $eticheta }}</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $valoare }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="border-t border-slate-100 p-6">
            <h2 class="text-sm font-semibold text-slate-900">Plata declarată</h2>
            <p class="mt-2 text-sm text-slate-600">
                {{ $policy->payment['method'] ?? '—' }} &middot;
                {{ number_format((float) ($policy->payment['amount'] ?? 0), 2, ',', '.') }}
                {{ $policy->payment['currency'] ?? '' }} &middot;
                document {{ $policy->payment['documentNumber'] ?? '—' }} &middot;
                {{ $policy->payment['date'] ?? '—' }}
            </p>

            @if($policy->installments)
                <h2 class="mt-6 text-sm font-semibold text-slate-900">Rate</h2>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    @foreach($policy->installments as $rata)
                        <li>
                            Rata {{ $rata['id'] ?? '?' }}:
                            {{ number_format((float) ($rata['amount'] ?? 0), 2, ',', '.') }} RON,
                            scadentă {{ $rata['dueDate'] ?? '—' }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if(session('eroare'))
        <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            {{ session('eroare') }}
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('polita.pdf', $policy) }}"
           class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
            Descarcă polița (PDF)
        </a>
        <a href="{{ route('oferta.show', $policy->offer->providerQuote->quoteRequest) }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Înapoi la oferte
        </a>
        <a href="{{ route('oferta.create') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Ofertă nouă
        </a>
    </div>

@endsection
