<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use App\Models\AuditEvent;
use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('istoric.index', [
            'quoteRequests' => QuoteRequest::query()
                ->where('user_id', $request->user()->id)
                ->with('providerQuotes.offers.policy')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, QuoteRequest $quoteRequest): View
    {
        // Urma unei cereri contine CNP-uri si payload-uri complete:
        // se vede doar de catre cel care a facut-o.
        abort_unless($quoteRequest->user_id === $request->user()->id, 404);

        $quoteRequest->load('user', 'providerQuotes.offers');

        // Emiterea politei si descarcarea PDF-ului se intampla in alte request-uri
        // web, deci cu alt correlation_id. Le regasim dupa quote_request_id si
        // adunam toate firele care au atins aceasta cerere.
        $correlations = ApiLog::query()
            ->where('quote_request_id', $quoteRequest->id)
            ->distinct()
            ->pluck('correlation_id')
            ->push($quoteRequest->correlation_id)
            ->filter()
            ->unique();

        return view('istoric.detaliu', [
            'quoteRequest' => $quoteRequest,

            'logs' => ApiLog::query()
                ->where('quote_request_id', $quoteRequest->id)
                ->orWhereIn('correlation_id', $correlations)
                ->orderBy('id')
                ->get(),

            'events' => AuditEvent::query()
                ->whereIn('correlation_id', $correlations)
                ->orderBy('id')
                ->get(),
        ]);
    }
}
