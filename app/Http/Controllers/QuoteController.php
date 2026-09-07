<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Models\County;
use App\Models\Locality;
use App\Models\QuoteRequest;
use App\Services\Rca\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        // Buton de test, doar in mediul local. Punem datele in "old input" si
        // redirectionam: fiecare camp le ia singur prin old(), fara sa atingem Blade-ul.
        if ($request->boolean('demo') && app()->environment('local')) {
            $request->session()->flashInput($this->demoInput());

            return redirect()->route('oferta.create');
        }

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

    /** Datele reale din documentatia API-ului, pentru testare rapida. */
    private function demoInput(): array
    {
        return [
            'motor' => [
                'startDate'        => now()->addDays(16)->toDateString(),
                'termTime'         => '12',
                'installmentCount' => '1',
            ],
            'policyholder' => [
                'lastName'     => 'Giosu',
                'firstName'    => 'Robert',
                'taxId'        => '5050518020094',
                'gender'       => 'm',
                'birthdate'    => '2005-05-18',
                'mobileNumber' => '0744444444',
                'email'        => 'robertgiosu@email.com',
                'identification' => [
                    'idType'         => 'CI',
                    'idNumber'       => 'ZR088130',
                    'issueAuthority' => 'SPCLEP Arad',
                    'issueDate'      => '2023-05-23',
                ],
                'drivingLicense' => ['issueDate' => '2023-10-13'],
                'address' => [
                    'county'      => 'AR',
                    'city'        => 'ARAD',
                    'street'      => 'Coriolan Petreanu',
                    'houseNumber' => '38',
                    'floor'       => '1',
                    'postcode'    => '310151',
                ],
            ],
            'vehicle' => [
                'licensePlate'       => 'AR08WSX',
                'registrationType'   => 'registered',
                'vin'                => 'WVWZZZ1KZ8W006165',
                'brand'              => 'Volkswagen',
                'model'              => 'Golf',
                'vehicleType'        => 'M1',
                'yearOfConstruction' => '2008',
                'firstRegistration'  => '2008-03-06',
                'fuelType'           => 'diesel',
                'engineDisplacement' => '1896',
                'enginePower'        => '77',
                'totalWeight'        => '1920',
                'seats'              => '5',
                'currentMileage'     => '226000',
                'usageType'          => 'personal',
                'identification'     => ['idNumber' => 'G205791'],
            ],
            'options' => [
                'driverIsPolicyholder' => '1',
                'expirationDatePti'    => now()->addMonths(6)->toDateString(),
                'bonusMalusClass'      => 'B0',
            ],
        ];
    }
}
