<?php

namespace App\Http\Controllers\Auth;

use App\Audit\AuditTrail;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\TwoFactorSession;
use App\Support\EmailNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // PostgreSQL compara varchar com distincao de caixa. Normalizar antes
        // da autenticacao e da atribuicao de tentativas falhas na auditoria.
        // Aceita o e-mail ou o nome de usuário curto (ex.: "admin").
        $credentials = [
            'email' => EmailNormalizer::fromLogin((string) $request->input('email')),
            'password' => (string) $request->input('password'),
        ];
        if (! filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! Auth::attempt([...$credentials, 'status' => 'active'])) {
            try {
                // Se o e-mail pertence a uma conta conhecida, a tentativa
                // precisa ser atribuída a ela, sem revelar sua existência
                // na resposta nem registrar a senha informada.
                $knownUserId = User::query()->where('email', $credentials['email'])->value('id');
                app(AuditTrail::class)->record(
                    connection: 'platform',
                    action: 'auth.failed',
                    auditableType: User::class,
                    auditableId: $knownUserId,
                    userId: $knownUserId,
                );
            } catch (Throwable $exception) {
                // Sem auditoria a tentativa não pode aparentar sucesso
                // operacional; uma falha de infraestrutura vira 503.
                report($exception);
                abort(503, 'Acesso temporariamente indisponível. Tente novamente mais tarde.');
            }

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        app(TwoFactorSession::class)->clear($request);
        try {
            app(AuditTrail::class)->record('platform', 'auth.login', User::class, (string) Auth::id());
        } catch (Throwable $exception) {
            // Nunca manter uma sessão autenticada sem o registro obrigatório.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            report($exception);
            abort(503, 'Acesso temporariamente indisponível. Tente novamente mais tarde.');
        }

        if (Auth::user()->two_factor_confirmed_at !== null) {
            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            app(AuditTrail::class)->record('platform', 'auth.logout', User::class, (string) Auth::id());
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            // A sessão precisa ser encerrada mesmo se a auditoria estiver indisponível.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
