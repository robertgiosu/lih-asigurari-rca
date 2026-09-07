<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Models\County;
use App\Models\Locality;
use App\Models\QuoteRequest;
use App\Services\Rca\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function create(): View
    {
        return view('oferta.form', [
            'counties' => County::orderBy('name')->get(),
        ]);
    }

    public function store(StoreQuoteRequest $request, OfferService $offers): RedirectResponse
    {
        // Pool-ul asteapta pana la RCA_TIMEOUT secunde per asigurator; implicitul
        // de 30s al serverului web nu ajunge cand mai multi raspund greu.
        set_time_limit(config('rca.timeout') + 60);

        // validated() intoarce exact structura imbricata pe care o asteapta OfferService.
        $quoteRequest = $offers->quote($request->validated(), $request->user()?->id);

        return redirect()->route('oferta.show', $quoteRequest);
    }

    public function show(QuoteRequest $quoteRequest): View
    {
        $quoteRequest->load('providerQuotes.offers');

        return view('oferta.rezultate', [
            'quoteRequest' => $quoteRequest,
            'offers'       => $quoteRequest->providerQuotes
                ->flatMap->offers
                ->sortBy(fn ($offer) => (float) $offer->premium_amount)
                ->values(),
            'esuate'       => $quoteRequest->providerQuotes
                ->where('status', 'error')
                ->sortBy('provider')
                ->values(),
        ]);
    }

    /** Alimenteaza dropdown-ul de localitati din formular. */
    public function localities(string $county): JsonResponse
    {
        $localitati = Locality::where('county_code',
            strtoupper($county))
            ->orderBy('rang')
            ->orderBy('name')
            ->get()
            ->map(fn (Locality $locality) => [
                // 'name' pleaca spre API, 'display' se vede in dropdown.
                'name'    => $locality->name,
                'display' => $locality->displayName(),
                'siruta'  => $locality->siruta,
            ]);

        return response()->json($localitati);
    }
}
