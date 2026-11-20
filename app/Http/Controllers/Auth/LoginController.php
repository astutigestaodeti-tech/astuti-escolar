<?php

namespace App\Http\Controllers\Auth;

use App\Audit\AuditTrail;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'status' => 'active'])) {
            app(AuditTrail::class)->record('platform', 'auth.failed', User::class);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        app(AuditTrail::class)->record('platform', 'auth.login', User::class, (string) Auth::id());

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(AuditTrail::class)->record('platform', 'auth.logout', User::class, (string) Auth::id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
