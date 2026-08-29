<?php

namespace App\Models;

use App\Support\Correlation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/*
 * Lista de coloane care pot fi completate prin mass assignment, adica prin create([...]) sau fill([...]) cu un array.
 * Aceasta este o protectie pentru ca un atacator ar putea trimite in formular un camp is_admin=1 si ar putea ajunge in
 * baza de date. Eloquent (ORM-ul) refuza implicit orice camp care nu e pe lista.
 */
#[Fillable([
    'correlation_id',
    'user_id',
    'session_id',
    'event',
    'subject_type',
    'subject_id',
    'payload',
    'ip',
    'user_agent',
])]
class AuditEvent extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array // cast-urile spun lui Eloquent cum sa converteasca intre formatul din DB si tipurile PHP
    {
        return [
            'payload'    => 'array', // coloana e JSON in baza de date, ii dai array PHP
            'created_at' => 'datetime',
        ];
    }

    /**
     * Inregistreaza o actiune a utilizatorului.
     *
     * Contextul (cine, de unde, cu ce sesiune) se completeaza singur, ca sa nu
     * fie nevoie sa-l repeti in fiecare controller.
     */
    public static function record(string $event, ?Model $subject = null, array $payload = []): self
    {
        $request = request();

        return static::create([ // facem insert si returnam modelul
            'correlation_id' => Correlation::id(),
            'user_id'        => Auth::id(),
            'session_id'     => $request->hasSession() ? $request->session()->getId() : null,
            'event'          => $event,
            'subject_type'   => $subject?->getMorphClass(),
            'subject_id'     => $subject?->getKey(),
            'payload'        => $payload ?: null,
            'ip'             => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);
    }
}
