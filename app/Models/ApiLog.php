<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'correlation_id',
    'user_id',
    'quote_request_id',
    'provider',
    'method',
    'url',
    'request_headers',
    'request_body',
    'response_status',
    'response_body',
    'duration_ms',
    'error',
    'ip',
])]
class ApiLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body'    => 'array',
            'response_body'   => 'array',
            'created_at'      => 'datetime',
        ];
    }
}
