<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $date = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:120',
                Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Parola se hashuieste automat: castul 'hashed' din modelul User.
        $user = User::create($date);

        Auth::login($user);

        // Sesiunea primeste un id nou dupa autentificare, impotriva session fixation.
        $request->session()->regenerate();

        AuditEvent::record('user.registered', $user);

        return redirect()->route('oferta.create')
            ->with('mesaj', 'Contul a fost creat. Bine ai venit!');
    }
}
