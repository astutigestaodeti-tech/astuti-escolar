<?php

namespace App\Http\Middleware;

use App\Security\TwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige o segundo fator confirmado para gravar.
 *
 * Sem parâmetro, basta a verificação feita na entrada do sistema.
 * Com o parâmetro "recente" (ações delicadas), o código precisa ter sido
 * digitado nos últimos 15 minutos; se não foi, o usuário vai para a tela do
 * código e depois volta ao formulário com os campos preenchidos.
 */
class RequireConfirmedTwoFactor
{
    public const RECENT = 'recente';

    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $user = $request->user()?->fresh();
        $twoFactor = app(TwoFactorSession::class);

        if ($user !== null && $request->isMethod('GET') && ! $twoFactor->isVerified($request, $user)) {
            $twoFactor->rememberDestination($request);

            return redirect()->route('two-factor.challenge');
        }

        if ($user === null || ! $twoFactor->isVerified($request, $user)) {
            abort(403, 'Confirme o segundo fator de autenticação antes de alterar registros escolares.');
        }

        if ($level === self::RECENT && ! $twoFactor->isRecentlyVerified($request, $user)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Esta ação exige confirmar o segundo fator novamente.'], 403);
            }

            $twoFactor->rememberDestination(
                $request,
                'Esta ação pede uma nova confirmação. Digite o código do aplicativo para continuar.',
            );

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
