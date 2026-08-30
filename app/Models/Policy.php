<?php

namespace App\Models;

use App\Models\Concerns\HasProviderLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'offer_id',
    'user_id',
    'provider',
    'api_policy_id',
    'series',
    'number',
    'premium_amount',
    'currency',
    'start_date',
    'end_date',
    'payment',
    'installments',
    'pdf_path',
    'raw',
])]
class Policy extends Model
{
    use HasProviderLabel;

    protected function casts(): array
    {
        return [
            'premium_amount' => 'decimal:2',
            'start_date'     => 'date',
            'end_date'       => 'date',
            'payment'        => 'array',
            'installments'   => 'array',
            'raw'            => 'array',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
