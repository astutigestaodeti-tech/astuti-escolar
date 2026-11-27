<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Developer\AgentAccess;
use App\Developer\DeploymentHistory;
use App\Developer\DeploymentSnapshot;
use App\Models\User;
use App\Security\TwoFactorSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class DeveloperAgentController extends Controller
{
    public function index(Request $request): Response
    {
        if ($denied = $this->authorizeRead($request, false)) {
            return $denied;
        }

        return $this->privateResponse(response()->view('developer.agent', $this->data($request)));
    }

    public function snapshot(Request $request): Response
    {
        if ($denied = $this->authorizeRead($request, true)) {
            return $denied;
        }

        $data = $this->data($request);

        return $this->privateResponse(response()->json([
            'html' => view('developer.partials.agent-history', $data)->render(),
            'available' => $data['snapshot']['available'],
            'stale' => $data['snapshot']['stale'],
        ]));
    }

    private function authorizeRead(Request $request, bool $json): ?Response
    {
        $user = $request->user()?->fresh();
        if (! app(AgentAccess::class)->allows($user)) {
            return $this->privateResponse(response('Acesso não autorizado.', 403));
        }

        $twoFactor = app(TwoFactorSession::class);
        if (! $twoFactor->isVerifiedWithoutBypass($request, $user)) {
            if ($json) {
                return $this->privateResponse(response()->json(['message' => 'Confirme o segundo fator novamente.'], 403));
            }

            $twoFactor->rememberDestination($request, 'A aba Agente exige confirmação real do segundo fator.');

            return $this->privateResponse(redirect()->route('two-factor.challenge'));
        }

        try {
            app(AuditTrail::class)->record(
                'platform',
                $json ? 'developer.agent.snapshot' : 'developer.agent.view',
                User::class,
                (string) $user->getKey(),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->privateResponse(response('Auditoria temporariamente indisponível.', 503));
        }

        return null;
    }

    private function data(Request $request): array
    {
        $snapshot = app(DeploymentSnapshot::class)->read(now()->getTimestamp());
        $stages = DeploymentSnapshot::stages();
        $stage = $request->query('stage');
        $stage = is_string($stage) && isset($stages[$stage]) ? $stage : '';
        $groups = app(DeploymentHistory::class)->group($snapshot['events'], $stage);
        $total = count($groups);
        $pages = max(1, (int) ceil($total / 10));
        $requested = $request->query('page');
        $page = is_string($requested) && ctype_digit($requested) ? min($pages, max(1, (int) $requested)) : 1;
        $groups = array_slice($groups, ($page - 1) * 10, 10);

        return [
            'municipality' => $request->attributes->get('astuti.municipality'),
            'snapshot' => $snapshot,
            'groups' => $groups,
            'stages' => $stages,
            'stage' => $stage,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
        ];
    }

    private function privateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
