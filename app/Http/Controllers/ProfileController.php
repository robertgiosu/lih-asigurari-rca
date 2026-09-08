<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profil.edit', [
            // Un profil gol, ca sa nu verificam null la fiecare camp din Blade.
            'profile'  => $request->user()->profile ?? new Profile,
            'counties' => County::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $date = $request->validated();

        $profile = $request->user()->profile()->updateOrCreate([], $date);

        // In audit retinem CE s-a schimbat, niciodata valorile: contin CNP si serie de act.
    AuditEvent::record('profile.updated', $profile, [
        'campuri' => array_keys($date),
    ]);

        return redirect()->route('profil.edit')->with('mesaj', 'Datele au
  fost salvate.');
    }
}
