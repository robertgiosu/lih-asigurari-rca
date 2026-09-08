<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $date = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($date, $request->boolean('remember'))) {
            // Incercarile esuate se inregistreaza, dar fara parola.
            AuditEvent::record('user.login_failed', null, ['email' =>
                $date['email']]);

            throw ValidationException::withMessages([
                'email' => 'Datele de autentificare nu sunt corecte.',
            ]);
        }

        $request->session()->regenerate();

        AuditEvent::record('user.login', $request->user());

        return redirect()->intended(route('oferta.create'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditEvent::record('user.logout', $request->user());

        Auth::logout();

        // Sesiunea veche nu mai trebuie sa poata fi refolosita.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('oferta.create');
    }
}
