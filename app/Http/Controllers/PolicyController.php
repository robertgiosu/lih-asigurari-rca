<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Policy;
use App\Models\QuoteRequest;
use App\Services\Rca\PolicyService;
use App\Services\Rca\PdfService;
use App\Services\Rca\RcaException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PolicyController extends Controller
{
    public function store(
        Request $request,
        QuoteRequest $quoteRequest,
        Offer $offer,
        PolicyService $policies,
    ): RedirectResponse {
        // Oferta trebuie sa apartina cererii din URL, altfel oricine poate emite
          // pe baza unui id de oferta ghicit.
          abort_unless($offer->providerQuote->quote_request_id ===
              $quoteRequest->id, 404);

          set_time_limit(config('rca.timeout') + 60);

          try {
              $policy = $policies->issue($offer,
                  $request->boolean('includeDirectCompensation'));
          } catch (RcaException $e) {
              return back()->with('eroare', 'Emiterea nu a reușit:
  '.$e->getMessage());
          }

          return redirect()->route('polita.show', $policy);
      }

    public function show(Policy $policy): View
    {
        return view('polita.detaliu', [
            'policy' => $policy->load('offer.providerQuote.quoteRequest'),
        ]);
    }

    /** Descarca PDF-ul politei. */
    public function pdf(Policy $policy, PdfService $pdfs): StreamedResponse|RedirectResponse
    {
        try {
            $path = $pdfs->forPolicy($policy);
        } catch (RcaException $e) {
            return back()->with('eroare', 'PDF-ul nu a putut fi descarcat: '.$e->getMessage());
        }

        $nume = 'polita-'.Str::slug($policy->series.'-'.$policy->number).'.pdf';

        return Storage::download($path, $nume);
    }
}
