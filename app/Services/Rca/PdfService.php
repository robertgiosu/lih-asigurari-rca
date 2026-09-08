<?php

namespace App\Services\Rca;

use App\Models\AuditEvent;
use App\Models\Offer;
use App\Models\Policy;
use Illuminate\Support\Facades\Storage;

/**
 * Descarca documentele PDF de la API.
 *
 * Atentie: endpoint-urile NU intorc un PDF binar, ci JSON cu fisierele codate
 * base64 in data.files[]. De aceea decodam si scriem noi fisierul pe disc.
 */
class PdfService
{
    public function __construct(private readonly RcaClient $client)
    {
    }

    /** @return string calea relativa pe discul 'local' */
    public function forOffer(Offer $offer): string
    {
        if ($path = $this->cached($offer->pdf_path)) {
            return $path;
        }

        $data = $this->client->get(
            '/offer/'.$offer->api_offer_id,
            quoteRequestId: $offer->providerQuote->quote_request_id,
            provider: $offer->provider,
        );

        $path = $this->store($data, 'oferte/oferta-'.$offer->api_offer_id, $offer->provider);

        $offer->update(['pdf_path' => $path]);

        AuditEvent::record('pdf.downloaded', $offer, ['tip' => 'oferta', 'provider' => $offer->provider]);

        return $path;
    }

    /** @return string calea relativa pe discul 'local' */
    public function forPolicy(Policy $policy): string
    {
        if ($path = $this->cached($policy->pdf_path)) {
            return $path;
        }

        $data = $this->client->get(
            '/policy/'.$policy->api_policy_id,
            quoteRequestId: $policy->offer->providerQuote->quote_request_id,
            provider: $policy->provider,
        );

        $path = $this->store($data, 'polite/polita-'.$policy->api_policy_id, $policy->provider);

        $policy->update(['pdf_path' => $path]);

        AuditEvent::record('pdf.downloaded', $policy, ['tip' => 'polita', 'provider' => $policy->provider]);

        return $path;
    }

    /** Un PDF descarcat o data nu se mai cere de la asigurator. */
    private function cached(?string $path): ?string
    {
        return $path && Storage::exists($path) ? $path : null;
    }

    private function store(array $data, string $prefix, string $provider): string
    {
        $file = $data['files'][0] ?? null;

        if (! $file || empty($file['content'])) {
            throw new RcaException('Asiguratorul nu a returnat niciun fisier.', provider: $provider);
        }

        // strict: true respinge orice nu e base64 valid, in loc sa scrie gunoi pe disc.
        $binary = base64_decode($file['content'], strict: true);

        if ($binary === false || $binary === '') {
            throw new RcaException('Fisierul primit nu a putut fi decodat.', provider: $provider);
        }

        $path = 'rca/'.$prefix.'.pdf';

        Storage::put($path, $binary);

        return $path;
    }
}
