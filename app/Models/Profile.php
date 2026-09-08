<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'last_name', 'first_name', 'tax_id', 'birthdate', 'gender',
    'mobile_number',
    'id_type', 'id_number', 'id_issue_authority', 'id_issue_date',
    'driving_license_issue_date',
    'county', 'city', 'street', 'house_number', 'building', 'staircase',
    'apartment', 'floor', 'postcode',
    'has_disability', 'is_retired',
])]
class Profile extends Model
{
    protected function casts(): array
    {
        return [
            // Laravel cripteaza la scriere si decripteaza la citire, transparent.
            'tax_id'                     => 'encrypted',
            'id_number'                  => 'encrypted',
            'birthdate'                  => 'date',
            'id_issue_date'              => 'date',
            'driving_license_issue_date' => 'date',
            'has_disability'             => 'boolean',
            'is_retired'                 => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Datele profilului pe cheile campurilor din formularul de oferta.
     * Cheile sunt identice cu numele folosite la validare.
     */
    public function toPrefill(): array
    {
        $valori = [
            'policyholder.lastName'                      => $this->last_name,
            'policyholder.firstName'                     =>
                $this->first_name,
            'policyholder.taxId'                         => $this->tax_id,
            'policyholder.gender'                        => $this->gender,
            'policyholder.birthdate'                     =>
                $this->birthdate?->toDateString(),
            'policyholder.email'                         =>
                $this->user?->email,
            'policyholder.mobileNumber'                  =>
                $this->mobile_number,
            'policyholder.identification.idType'         => $this->id_type,
            'policyholder.identification.idNumber'       => $this->id_number,
            'policyholder.identification.issueAuthority' =>
                $this->id_issue_authority,
            'policyholder.identification.issueDate'      =>
                $this->id_issue_date?->toDateString(),
            'policyholder.drivingLicense.issueDate'      =>
                $this->driving_license_issue_date?->toDateString(),
            'policyholder.address.county'                => $this->county,
            'policyholder.address.city'                  => $this->city,
            'policyholder.address.street'                => $this->street,
            'policyholder.address.houseNumber'           =>
                $this->house_number,
            'policyholder.address.building'              => $this->building,
            'policyholder.address.staircase'             => $this->staircase,
            'policyholder.address.apartment'             => $this->apartment,
            'policyholder.address.floor'                 => $this->floor,
            'policyholder.address.postcode'              => $this->postcode,
            'policyholder.hasDisability'                 =>
                $this->has_disability,
            'policyholder.isRetired'                     =>
                $this->is_retired,
        ];

        return array_filter($valori, fn ($valoare) => $valoare !== null &&
            $valoare !== '');
    }
}
