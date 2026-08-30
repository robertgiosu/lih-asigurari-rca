<?php

namespace App\Models;

use App\Models\Concerns\HasProviderLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'quote_request_id',
    'provider',
    'status',
    'http_status',
    'duration_ms',
    'error_message',
    'offers_count',
])]
class ProviderQuote extends Model
{
    use HasProviderLabel;

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function succeeded(): bool
    {
        return $this->status === 'ok';
    }

    /** Asiguratorii despre care stim ca nu raspund in QA (ex. DallBogg). */
    public function failureIsExpected(): bool
    {
        return (bool) config("rca.providers.{$this->provider}.expect_failure", false);
    }
}
