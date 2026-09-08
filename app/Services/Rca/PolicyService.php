<?php

namespace App\Services\Rca;

use App\Models\AuditEvent;
use App\Models\Offer;
use App\Models\Policy;
use Illuminate\Support\Facades\Auth;

class PolicyService
{
    public function __construct(private readonly RcaClient $client)
    {
    }

    public function issue(Offer $offer, bool $includeDirectCompensation
    = false): Policy
    {
        if ($offer->policy) { // verificam daca exista deja polita pentru oferta
            throw new RcaException('Pentru aceasta oferta exista deja o
  polita emisa.', provider: $offer->provider);
        }

        $payment = $this->payment($offer, $includeDirectCompensation);

        $data = $this->client->post( // facem post
            '/policy',
            [
                'offerId'                   => $offer->api_offer_id,
                'includeDirectCompensation' =>
                    $includeDirectCompensation,
                'payment'                   => $payment,
            ],
            provider: $offer->provider,
            quoteRequestId: $offer->providerQuote->quote_request_id,
        );

        $raw = $data['policies'][0] ?? null;

        if (! $raw) {
            throw new RcaException('Asiguratorul nu a returnat nicio
  polita.', provider: $offer->provider);
        }

        $policy = $offer->policy()->create([
                'user_id'        => Auth::id(),
                'provider'       => $offer->provider,
                'api_policy_id'  => $raw['policyId'],
                'series'         => $raw['series'] ?? null,
                // 'number' vine numeric de la API; il tinem text (vezi migrarea).
            'number'         => isset($raw['number']) ? (string)
    $raw['number'] : null,
              'premium_amount' => $raw['premiumAmount'] ??
        $payment['amount'],
              'currency'       => $raw['currency'] ?? 'RON',
              'start_date'     => $raw['startDate'] ?? null,
              'end_date'       => $raw['endDate'] ?? null,
              // Ce a confirmat asiguratorul; daca nu confirma nimic, ce am trimis noi.
    'payment'        => $raw['payment'] ?? $payment,
              'installments'   => $raw['installments'] ?? null,
              'raw'            => $raw,
          ]);

AuditEvent::record('policy.issued', $policy, [ // inregistram policy-ul
    'provider' => $policy->provider,
    'series'   => $policy->series,
    'number'   => $policy->number,
    'amount'   => (float) $policy->premium_amount,
]);

          return $policy;
      }

    /**
     * Obiectul 'payment' cerut obligatoriu de API. Nu e o plata reala:
    declara
     * catre asigurator cum a fost incasata prima.
     */
    private function payment(Offer $offer, bool
                                   $includeDirectCompensation): array
    {
        $amount = $includeDirectCompensation
            ? ($offer->direct_compensation['premiumAmount'] ??
                $offer->premium_amount)
            : $offer->premium_amount;

        return [
            'method'         => 'broker receipt',
            'currency'       => $offer->currency ?: 'RON',
            'amount'         => round((float) $amount, 2),
            'date'           => now()->toDateString(),
            // Derivat din id-ul local: acelasi numar la o reincercare, si urmaribil.
    'documentNumber' => sprintf('RCA-%06d', $offer->id),
          ];
      }
}
