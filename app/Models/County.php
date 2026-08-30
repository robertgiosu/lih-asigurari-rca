<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'siruta'])]
#[RouteKey('code')]
class County extends Model
{
    public function localities(): HasMany // cheia straina
    {
        // Relatia se face pe 'code', nu pe 'id': API-ul lucreaza cu coduri de judet.
        return $this->hasMany(Locality::class, 'county_code', 'code'); // ia din localities toate randurile uinde county/code este egal cu code-ul judetului curent
    }

    /** Numele pentru interfata. 'name' ramane neatins - el pleaca spre API. */
    public function displayName(): string
    {
        return Str::title($this->name);
    }
}
