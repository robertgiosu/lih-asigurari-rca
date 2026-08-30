<?php

namespace App\Services\Rca;

use App\Models\AuditEvent;
use App\Models\ProviderQuote;
use App\Models\QuoteRequest;
use App\Support\Correlation;

class OfferService
{
    public function __construct(
        private readonly RcaClient $client,
        private readonly OfferPayloadBuilder $builder,
    ) {
    }

    public function quote(array $input, ?int $userId = null): QuoteRequest
    {
        $quoteRequest = $this->recordRequest($input, $userId); // Salveaza cererea in DB. Inainte de apelul HTTP.

        $calls = [];

        foreach (array_keys(config('rca.providers')) as $provider) { // Construieste payload-urile. Cate unul pentru fiecare asigurator din config.
            $calls[$provider] = [
                'path'    => '/offer',
                'payload' => $this->builder->build($provider, $input),
            ];
        }

        $results = $this->client->pool($calls, $quoteRequest->id); // Le trimite pe toate deodata prin pool catre endpoint-ul /offer

        $totalOffers = 0;

        foreach ($results as $provider => $result) { // salveaza rezultatele, un rand in provider_quotes - indiferent daca a mers sau nu.
            $providerQuote = $quoteRequest->providerQuotes()->create([
                'provider'      => $provider,
                'status'        => $result->ok ? 'ok' : 'error',
                'http_status'   => $result->httpStatus,
                'duration_ms'   => $result->durationMs,
                'error_message' => $result->error,
            ]);

            if ($result->ok) {
                $count = $this->storeOffers($providerQuote, $result->data['offers'] ?? []);
                $providerQuote->update(['offers_count' => $count]);
                $totalOffers += $count;
            }
        }

        $quoteRequest->update(['status' => $totalOffers > 0 ? 'completed' : 'failed']);

        return $quoteRequest->fresh(); // se returneaza cererea (completed daca exista macar o oferta, failed daca niciuna)
    }

    /**
     * Scrie cererea in baza INAINTE de orice apel la API.
     * Asa, ce a completat utilizatorul ramane salvat chiar daca toti asiguratorii cad.
     */
    private function recordRequest(array $input, ?int $userId): QuoteRequest
    {
        $request = request();

        $quoteRequest = QuoteRequest::create([
            'correlation_id'    => Correlation::id(),
            'user_id'           => $userId,
            'input'             => $input,
            'status'            => 'pending',
            'license_plate'     => $input['vehicle']['licensePlate'] ?? null,
            'policyholder_name' => trim(($input['policyholder']['lastName'] ?? '').'
  '.($input['policyholder']['firstName'] ?? '')),
            'start_date'        => $input['motor']['startDate'] ?? null,
            'ip'                => $request->ip(),
            'user_agent'        => $request->userAgent(),
            'session_id'        => $request->hasSession() ? $request->session()->getId() : null,
        ]);

        AuditEvent::record('quote.requested', $quoteRequest, [
            'licensePlate' => $quoteRequest->license_plate,
            'providers'    => array_keys(config('rca.providers')),
        ]);

        return $quoteRequest;
    }

    private function storeOffers(ProviderQuote $providerQuote, array $offers): int
    {
        foreach ($offers as $offer) {
            $providerQuote->offers()->create([ // face insert in DB la oferta
                'provider'              => $providerQuote->provider,
                'api_offer_id'          => $offer['offerId'],
                'provider_offer_code'   => $offer['providerOfferCode'] ?? null,
                'premium_amount'        => $offer['premiumAmount'],
                'premium_amount_net'    => $offer['premiumAmountNet'] ?? null,
                'currency'              => $offer['currency'] ?? 'RON',
                'start_date'            => $offer['startDate'] ?? null,
                'end_date'              => $offer['endDate'] ?? null,
                'reference_rate'        => $offer['referenceRate'] ?? null,
                'bonus_malus_class'     => $offer['bonusMalusClass'] ?? null,
                'commission_value'      => $offer['commissionValue'] ?? null,
                'commission_percent'    => $offer['commissionPercent'] ?? null,
                'direct_compensation'   => $offer['directCompensation'] ?? null,
                'installments'          => $offer['installments'] ?? null,
                'green_card_exclusions' => $offer['greenCardExclusions'] ?? null,
                'notes'                 => $offer['notes'] ?? null,
                'offer_expiry_date'     => $offer['offerExpiryDate'] ?? null,
                'pid'                   => $offer['pid'] ?? null,
                'toc'                   => $offer['toc'] ?? null,
                'payment_link'          => $offer['paymentLink'] ?? null,
                // Oferta intreaga, ca sa nu pierdem campuri pe care nu le-am anticipat.
                'raw'                   => $offer,
            ]);
        }

        return count($offers);
    }
}
