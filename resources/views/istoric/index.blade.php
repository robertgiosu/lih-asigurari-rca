@extends('layouts.app')

@section('title', 'Istoricul cererilor')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Istoricul cererilor</h1>
        <p class="mt-1 text-sm text-slate-600">
            Fiecare cerere de ofertă, cu tot ce s-a completat și tot ce a plecat spre asigurători.
        </p>
    </div>

    @if($quoteRequests->isEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-8 text-center">
            <p class="text-sm text-slate-600">Nu ai cerut încă nicio ofertă.</p>
            <a href="{{ route('oferta.create') }}"
               class="mt-4 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                Cere prima ofertă
            </a>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Vehicul</th>
                        <th class="px-4 py-3">Asigurat</th>
                        <th class="px-4 py-3">Stare</th>
                        <th class="px-4 py-3 text-right">Oferte</th>
                        <th class="px-4 py-3 text-right">Cel mai bun preț</th>
                        <th class="px-4 py-3">Poliță</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($quoteRequests as $cerere)
                        @php
                            $oferte = $cerere->providerQuotes->flatMap->offers;
                            $celMaiBun = $oferte->min('premium_amount');
                            $polita = $oferte->firstWhere('policy');
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ $cerere->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $cerere->license_plate ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $cerere->policyholder_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($cerere->status === 'completed')
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">finalizată</span>
                                @elseif($cerere->status === 'failed')
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">fără oferte</span>
                                @else
                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">întreruptă</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $oferte->count() }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">
                                @if($celMaiBun)
                                    {{ number_format((float) $celMaiBun, 2, ',', '.') }} RON
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($polita?->policy)
                                    <a href="{{ route('polita.show', $polita->policy) }}"
                                       class="text-emerald-700 hover:underline">{{ $polita->policy->series }}</a>
                                @else
                                    <span class="text-slate-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('istoric.show', $cerere) }}"
                                   class="whitespace-nowrap text-sm font-medium text-sky-600 hover:underline">
                                    Vezi urma
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $quoteRequests->links() }}
        </div>
    @endif

@endsection
