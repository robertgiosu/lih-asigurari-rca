@extends('layouts.app')

@section('title', 'Oferte RCA')

@section('content')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Oferte
                pentru {{ $quoteRequest->license_plate }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $quoteRequest->policyholder_name }} &middot;
                începe la {{
  $quoteRequest->start_date?->format('d.m.Y') }} &middot;
                {{ $offers->count() }} {{ $offers->count() === 1 ?
  'ofertă' : 'oferte' }}
            </p>
        </div>
        <a href="{{ route('oferta.create') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2
  text-sm font-medium hover:bg-slate-50">
            Ofertă nouă
        </a>
    </div>

    @if($offers->isEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6
  text-sm text-amber-900">
            Niciun asigurător nu a returnat o ofertă. Vezi motivele mai
            jos.
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200
  bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200
  text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase
  tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Asigurător</th>
                    <th class="px-4 py-3 text-right">Primă</th>
                    <th class="px-4 py-3 text-right">Cu decontare
                        directă</th>
                    <th class="px-4 py-3">Clasa B/M</th>
                    <th class="px-4 py-3">Valabilitate</th>
                    <th class="px-4 py-3">Documente</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @foreach($offers as $index => $offer)
                    <tr class="{{ $index === 0 ? 'bg-emerald-50/60'
  : '' }}">
                        <td class="px-4 py-3">
                                  <span class="font-medium
  text-slate-900">{{ $offer->providerLabel() }}</span>
                            @if($index === 0)
                                <span class="ml-2 rounded
  bg-emerald-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase
  text-white">
                                          cel mai ieftin
                                      </span>
                            @endif
                            @if($offer->notes)
                                <p class="mt-1 max-w-md text-xs
  text-slate-500">{{ Str::limit($offer->notes, 160) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right
  font-semibold tabular-nums">
                            {{ number_format((float)
                              $offer->premium_amount, 2, ',', '.') }} {{ $offer->currency }}
                        </td>
                        <td class="px-4 py-3 text-right
  tabular-nums text-slate-600">

                            @if($offer->direct_compensation['premiumAmount'] ?? null)
                                {{ number_format((float)
$offer->direct_compensation['premiumAmount'], 2, ',', '.') }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td class="px-4 py-3">{{
  $offer->bonus_malus_class ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $offer->start_date?->format('d.m.Y')
}} – {{ $offer->end_date?->format('d.m.Y') }}
                        </td>
                        <td class="px-4 py-3">
                            @if($offer->pid)
                                <a href="{{ $offer->pid }}"
                                   target="_blank" rel="noopener"
                                   class="text-sky-600
  hover:underline">PID</a>
                            @endif
                            @if($offer->toc)
                                <a href="{{ $offer->toc }}"
                                   target="_blank" rel="noopener"
                                   class="ml-2 text-sky-600
  hover:underline">Condiții</a>
                                @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($esuate->isNotEmpty())
        <div class="mt-6 rounded-xl border border-slate-200 bg-white
  p-6 shadow-sm">
            <h2 class="text-sm font-semibold
  text-slate-900">Asigurători fără ofertă</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($esuate as $providerQuote)
                    <li class="flex gap-3">
                          <span class="w-48 shrink-0 font-medium
  text-slate-700">
                              {{ $providerQuote->providerLabel() }}
                          </span>
                        <span class="text-slate-500">
                              @if($providerQuote->failureIsExpected())
                                Indisponibil în mediul de test.
                            @else
                                {{
Str::limit($providerQuote->error_message, 200) }}
                            @endif
                          </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
    <p class="mt-6 text-xs text-slate-400">
        Cerere {{ $quoteRequest->uuid }} &middot; {{
  $quoteRequest->created_at->format('d.m.Y H:i') }}
    </p>

@endsection
