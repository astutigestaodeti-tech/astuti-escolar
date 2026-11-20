<?php

namespace App\Audit;

use App\Models\Platform\Membership;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Tenant\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuditTrail
{
    /**
     * Registra o evento na conexão correta, sem persistir segredos ou laudos.
     * A conexão é deliberadamente explícita para evitar vazamento entre redes.
     */
    public function record(
        string $connection,
        string $action,
        ?string $auditableType = null,
        ?string $auditableId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $userId = null,
        ?string $role = null,
    ): void {
        if (! in_array($connection, ['tenant', 'platform'], true)) {
            throw new InvalidArgumentException('Destino de auditoria não permitido.');
        }

        if ($connection === 'tenant' && ! app(TenantContext::class)->hasActive()) {
            throw new InvalidArgumentException('Auditoria municipal exige contexto ativo.');
        }

        $actor = $userId ?? Auth::id();
        $request = app()->bound('request') ? request() : null;
        $requestId = $request?->attributes->get('astuti.request_id');
        if ($requestId === null) {
            $requestId = (string) Str::uuid();
            $request?->attributes->set('astuti.request_id', $requestId);
        }

        if ($connection === 'tenant' && $role === null && $actor !== null) {
            $municipality = app(TenantContext::class)->current();
            $role = Membership::query()
                ->where('municipality_id', $municipality->id)
                ->where('user_id', $actor)
                ->where('status', 'active')
                ->value('role');
        }

        $model = $connection === 'tenant' ? AuditLog::class : PlatformAuditLog::class;
        $model::query()->create([
            'id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'user_id' => $actor,
            'role' => $role,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'before' => self::redact($before),
            'after' => self::redact($after),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $requestId,
        ]);
    }

    private static function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (preg_match('/password|senha|token|secret|segredo|laudo|medical|saude|saúde|diagnos|cid|access.key/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }

        return $values;
    }
}
