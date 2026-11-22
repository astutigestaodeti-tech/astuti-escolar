<?php

namespace App\Http\Controllers\Auth;

use App\Audit\AuditTrail;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\Totp;
use App\Security\TotpQrCode;
use App\Security\TwoFactorSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->fresh();
        $qrCode = null;
        if ($user->two_factor_pending_secret && ! $user->two_factor_confirmed_at) {
            $totpQr = app(TotpQrCode::class);
            $qrCode = $totpQr->svg($totpQr->uri($user->email, $user->two_factor_pending_secret));
        }

        return view('auth.two-factor-settings', ['user' => $user, 'qrCode' => $qrCode]);
    }

    public function begin(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Senha atual incorreta.']);
        }

        DB::connection('platform')->transaction(function () use ($user, $totp): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->two_factor_confirmed_at !== null) {
                throw ValidationException::withMessages(['password' => 'O segundo fator já está ativado.']);
            }

            $locked->forceFill(['two_factor_pending_secret' => $totp->generateSecret()])->save();
            app(AuditTrail::class)->record('platform', 'auth.two_factor_started', User::class, $user->id);
        });

        app(TwoFactorSession::class)->clear($request);

        return redirect()->route('two-factor.settings');
    }

    public function confirm(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D']]);
        $user = $request->user();
        $recoveryCodes = [];

        DB::connection('platform')->transaction(function () use ($user, $totp, $data, &$recoveryCodes): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $secret = $locked->two_factor_pending_secret;
            if (! is_string($secret) || $locked->two_factor_confirmed_at !== null) {
                throw ValidationException::withMessages(['code' => 'Inicie a configuração novamente.']);
            }

            $step = $totp->matchingStep($secret, $data['code'], now()->getTimestamp());
            if ($step === null) {
                throw ValidationException::withMessages(['code' => 'Código inválido ou expirado.']);
            }

            for ($i = 0; $i < 8; $i++) {
                $recoveryCodes[] = strtoupper(bin2hex(random_bytes(8)));
            }

            $locked->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_pending_secret' => null,
                'two_factor_confirmed_at' => now(),
                'two_factor_last_step' => $step,
                'two_factor_recovery_codes' => array_map(
                    fn (string $code): string => hash('sha256', $code),
                    $recoveryCodes,
                ),
            ])->save();
            app(AuditTrail::class)->record('platform', 'auth.two_factor_enabled', User::class, $user->id);
        });

        app(TwoFactorSession::class)->markVerified($request, $user->fresh());

        return redirect()->route('two-factor.settings')->with('two_factor_recovery_codes', $recoveryCodes);
    }

    public function challengePage(Request $request): View|RedirectResponse
    {
        if ($request->user()->fresh()->two_factor_confirmed_at === null) {
            return redirect()->route('two-factor.settings');
        }

        return view('auth.two-factor-challenge');
    }

    public function challenge(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A(?:[0-9]{6}|[0-9A-Fa-f]{16})\z/D']]);
        $user = $request->user();

        DB::connection('platform')->transaction(function () use ($user, $totp, $data): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->two_factor_confirmed_at === null || ! is_string($locked->two_factor_secret)) {
                throw ValidationException::withMessages(['code' => 'Configure primeiro o segundo fator.']);
            }

            $code = strtoupper($data['code']);
            $step = $totp->matchingStep($locked->two_factor_secret, $code, now()->getTimestamp());

            if ($step !== null && ($locked->two_factor_last_step === null || $step > $locked->two_factor_last_step)) {
                $locked->forceFill(['two_factor_last_step' => $step])->save();
            } elseif (strlen($code) === 16) {
                $hash = hash('sha256', $code);
                $recovery = $locked->two_factor_recovery_codes ?? [];
                $index = array_search($hash, $recovery, true);

                if ($index === false) {
                    throw ValidationException::withMessages(['code' => 'Código inválido, já utilizado ou expirado.']);
                }

                unset($recovery[$index]);
                $locked->forceFill(['two_factor_recovery_codes' => array_values($recovery)])->save();
            } else {
                throw ValidationException::withMessages(['code' => 'Código inválido, já utilizado ou expirado.']);
            }

            app(AuditTrail::class)->record('platform', 'auth.two_factor_verified', User::class, $user->id);
        });

        $request->session()->regenerate();
        app(TwoFactorSession::class)->markVerified($request, $user->fresh());

        return redirect()->intended('/');
    }

    public function disable(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user()->fresh();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Senha atual incorreta.']);
        }

        if (! app(TwoFactorSession::class)->isVerified($request, $user)) {
            abort(403, 'Confirme o segundo fator antes de desativá-lo.');
        }

        DB::connection('platform')->transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->update([
                'two_factor_pending_secret' => null,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_step' => null,
            ]);
            app(AuditTrail::class)->record('platform', 'auth.two_factor_disabled', User::class, $user->id);
        });

        app(TwoFactorSession::class)->clear($request);

        return redirect()->route('two-factor.settings')->with('status', 'Segundo fator desativado. As alterações escolares permanecerão bloqueadas.');
    }
}
