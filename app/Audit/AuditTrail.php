<?php

namespace App\Audit;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class AuditTrail
{
    /** Nada fora desta lista chega ao JSON de auditoria. */
    private const ALLOWED_FIELDS = [
        School::class => ['id', 'name', 'inep_code', 'status', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        SchoolYear::class => ['id', 'year', 'start_date', 'end_date', 'status', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        SchoolClass::class => ['id', 'school_id', 'school_year_id', 'name', 'stage', 'modality', 'shift', 'capacity', 'status', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        Student::class => ['id', 'full_name', 'birth_date', 'ra', 'address_street', 'address_number', 'address_complement', 'address_district', 'address_city', 'address_state', 'postal_code', 'residence_zone', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        StudentEnrollment::class => ['id', 'student_id', 'school_id', 'school_year_id', 'school_class_id', 'status', 'started_at', 'ended_at', 'destination_note', 'transport_status', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        'student_import_receipt' => ['id', 'school_id', 'school_year_id', 'student_count', 'ignored_count'],
        'transport_import_receipt' => ['id', 'school_id', 'school_year_id', 'row_count', 'changed_count'],
        Municipality::class => ['status'],
    ];

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

        // Uma tentativa de senha incorreta pertence à conta informada,
        // nunca à sessão anterior ou ao contexto de um job em andamento.
        $actor = $action === 'auth.failed'
            ? $userId
            : ($userId ?? Auth::id() ?? app(AuditActor::class)->current());
        if ($connection === 'tenant' && $actor === null) {
            throw new LogicException('Uma gravação escolar exige autoria identificada, também em tarefas automáticas.');
        }
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
                ->pluck('role')->unique()->sort()->implode(',') ?: null;
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
            'before' => self::safeSnapshot($auditableType, $before),
            'after' => self::safeSnapshot($auditableType, $after),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $requestId,
        ]);
    }

    private static function safeSnapshot(?string $entity, ?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $allow = self::ALLOWED_FIELDS[$entity] ?? [];
        $values = array_intersect_key($values, array_flip($allow));

        foreach ($values as $key => $value) {
            if (preg_match('/\A(?:password|senha|token|secret|segredo|laudo|medical|saude|saúde|diagnos(?:tico)?|cid|access_key)\z/iu', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = '[REDACTED]';
            }
        }

        return $values;
    }
}
