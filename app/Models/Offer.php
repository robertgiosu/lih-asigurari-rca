<?php

namespace App\Models;

use App\Models\Concerns\HasProviderLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'provider_quote_id',
    'provider',
    'api_offer_id',
    'provider_offer_code',
    'premium_amount',
    'premium_amount_net',
    'currency',
    'start_date',
    'end_date',
    'reference_rate',
    'bonus_malus_class',
    'commission_value',
    'commission_percent',
    'direct_compensation',
    'installments',
    'green_card_exclusions',
    'notes',
    'offer_expiry_date',
    'pid',
    'toc',
    'payment_link',
    'pdf_path',
    'raw',
])]
class Offer extends Model
{
    // lipim metoda providerLabel() pe Offer, exact cum am fi scris-o direct aici, metoda devine disponibila pe orice obiect Offer.
    use HasProviderLabel;

    protected function casts(): array
    {
        return [
            'premium_amount'      => 'decimal:2',
            'premium_amount_net'  => 'decimal:2',
            'reference_rate'      => 'decimal:2',
            'commission_value'    => 'decimal:2',
            'commission_percent'  => 'decimal:2',
            'start_date'          => 'date',
            'end_date'            => 'date',
            'offer_expiry_date'   => 'date',
            'direct_compensation' => 'array',
            'installments'        => 'array',
            'raw'                 => 'array',
        ];
    }

    public function providerQuote(): BelongsTo
    {
        return $this->belongsTo(ProviderQuote::class);
    }

    public function policy(): HasOne
    {
        return $this->hasOne(Policy::class);
    }
}
