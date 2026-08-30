<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

/* Cand faci mass assignment (QuoteRequest::create($request->all()), atunci iei tot ce a trimis browserul si il bagi in model
 * Fillable e o protectia pentru ca field-urile care nu apar in lista sa fie ignorate.
 */
#[Fillable([
    'uuid',
    'correlation_id',
    'user_id',
    'input',
    'status',
    'license_plate',
    'policyholder_name',
    'start_date',
    'ip',
    'user_agent',
    'session_id',
])]
// RouteKey('uuid') va produce URL-uri din /quotes/1 in /quotes/8f14e45f-ceea-467a-9d5e-3c2a1b7f9012 (o metoda de ascundere)
#[RouteKey('uuid')]
/* Modelul QuoteRequest este stratul de mapare intre tabelul din baza de date si obiectele PHP cu care lucrezi in cod.
 * Migratia defineste structura tabelului, modelul spune cum o vezi in aplicatie.
 */
class QuoteRequest extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $quoteRequest) {
            $quoteRequest->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'input'      => 'array', // aici facem cast din baza de date in php (JSON -> array)
            'start_date' => 'date',
        ];
    }

    public function user(): BelongsTo // cheia straina
    {
        return $this->belongsTo(User::class); // obiectul legat de cheia straina
    }

    public function providerQuotes(): HasMany // cheia straina
    {
        return $this->hasMany(ProviderQuote::class); // obiectul legat de cheia straina
    }

    /** Toate ofertele cererii, indiferent de asigurator. */
    public function offers(): HasManyThrough // cheia straina
    {
        return $this->hasManyThrough(Offer::class, ProviderQuote::class); // obiectul legat de cheia straina
    }

    /** Apelurile HTTP facute pentru aceasta cerere. */
    public function apiLogs(): HasMany // cheia straina
    {
        return $this->hasMany(ApiLog::class); // obiectul legat de cheia straina
    }
}
