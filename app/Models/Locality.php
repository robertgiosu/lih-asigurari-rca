<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['county_code', 'name', 'rang', 'siruta'])]
class Locality extends Model
{
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class, 'county_code', 'code');
    }

    /** Numele pentru interfata. 'name' ramane neatins - el pleaca spre API.
     */
    public function displayName(): string
    {
        return Str::title($this->name);
    }
}
