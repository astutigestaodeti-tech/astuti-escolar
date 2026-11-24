<?php

namespace App\Audit;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\CensusParameter;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\IntegralFundebExpense;
use App\Models\Tenant\IntegralFundebFinance;
use App\Models\Tenant\IntegralPactTarget;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolMealMenu;
use App\Models\Tenant\SchoolMealServing;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentExit;
use App\Models\Tenant\StudentFinalResult;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\Tenant\StudentHealthProfile;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Models\Tenant\StudentPriorSchoolYear;
use App\Models\Tenant\StudentSpecialEducation;
use App\Models\Tenant\VaarCondition;
use App\Models\Tenant\WaitlistRequest;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class AuditTrail
{
    /** Nada fora desta lista chega ao JSON de auditoria. */
    private const ALLOWED_FIELDS = [
        SchoolMealMenu::class => ['id', 'school_year_id', 'week_start', 'weekday', 'stage', 'meal_type', 'description', 'updated_by'],
        SchoolMealServing::class => ['id', 'school_id', 'school_year_id', 'meal_date', 'stage', 'meal_type', 'quantity', 'suggested_present', 'confirmed_by', 'confirmed_at'],
        IntegralFundebExpense::class => ['id', 'school_year_id', 'description', 'amount_cents', 'created_by', 'has_evidence'],
        IntegralFundebFinance::class => ['id', 'school_year_id', 'revenue_cents', 'updated_by'],
        IntegralPactTarget::class => ['id', 'school_year_id', 'stage', 'target_enrollments', 'updated_by'],
        VaarCondition::class => ['id', 'reference_year', 'condition_number', 'status', 'responsible', 'updated_by', 'has_evidence'],
        CensusParameter::class => ['id', 'reference_year', 'vaaf_base', 'source_note', 'updated_by'],
        ClassYearClosure::class => ['school_class_id', 'closed_at', 'closed_by', 'close_reason', 'reopened_at', 'reopened_by', 'reopen_reason'],
        StudentFinalResult::class => ['id', 'school_class_id', 'student_enrollment_id', 'suggested_result', 'result', 'override_reason', 'confirmed_by', 'confirmed_at'],
        AcademicPeriod::class => ['id', 'school_year_id', 'kind', 'number', 'starts_on', 'ends_on', 'closed_at', 'closed_by', 'close_reason'],
        CurricularComponent::class => ['id', 'code', 'name', 'is_optional', 'active', 'display_order'],
        GradeEntry::class => ['id', 'student_enrollment_id', 'school_class_id', 'academic_period_id', 'curricular_component_id', 'grade', 'absences', 'recorded_by', 'updated_by', 'change_reason'],
        AttendanceSession::class => ['id', 'school_class_id', 'attendance_date', 'recorded_by', 'recorded_at', 'last_corrected_by', 'last_corrected_at', 'correction_reason', 'present_count', 'absent_count', 'justified_count'],
        ClassTeacherAssignment::class => ['id', 'school_class_id', 'staff_member_id', 'subject', 'is_main', 'starts_on', 'ends_on'],
        StaffMember::class => ['id', 'school_id', 'full_name', 'registration_number', 'role', 'institutional_email', 'phone', 'status', 'deleted_at', 'deleted_by', 'deletion_reason', 'created_at', 'updated_at'],
        AttendanceMark::class => ['id', 'attendance_session_id', 'student_id', 'status'],
        Guardian::class => ['id', 'full_name', 'cpf', 'phone', 'email', 'phone_secondary', 'workplace', 'work_phone', 'photo_updated_at', 'rg_number', 'rg_state'],
        // Educação especial: só quais campos mudaram, nunca os valores.
        StudentSpecialEducation::class => ['student_id', 'changed_fields'],
        StudentPriorSchoolYear::class => ['id', 'student_id', 'school_year', 'school_stage', 'result', 'attendance_percent', 'grades_changed', 'deleted_at', 'deleted_by'],
        IssuedDocument::class => ['id', 'student_id', 'school_id', 'enrollment_id', 'issued_by', 'issued_at', 'verification_code', 'revoked_at', 'revoked_by', 'revoke_reason'],
        StudentGuardian::class => ['id', 'student_id', 'guardian_id', 'relationship', 'is_legal_guardian', 'is_primary_contact', 'motivo'],
        StudentHealthProfile::class => ['student_id', 'blood_type', 'informed_by', 'informed_on'],
        StudentHealthAlert::class => ['id', 'student_id', 'kind', 'severity', 'alert_text', 'care_instructions', 'motivo'],
        StudentExit::class => ['id', 'student_id', 'school_id', 'person_type', 'person_id', 'person_name', 'person_relationship', 'exited_at', 'cancelled_at', 'cancelled_by', 'cancel_reason'],
        StudentPickupAuthorization::class => ['id', 'student_id', 'full_name', 'relationship', 'phone', 'document', 'expires_at', 'notes', 'motivo', 'photo_updated_at'],
        SchoolCalendarDay::class => ['id', 'school_year_id', 'school_id', 'date', 'kind', 'created_at', 'updated_at'],
        School::class => ['id', 'name', 'inep_code', 'status', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at', 'latitude', 'longitude', 'geo_source', 'geo_updated_at', 'postal_code', 'address_street', 'address_number', 'address_complement', 'address_district', 'address_city', 'address_state'],
        SchoolYear::class => ['id', 'year', 'start_date', 'end_date', 'status', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        SchoolClass::class => ['id', 'school_id', 'school_year_id', 'name', 'stage', 'modality', 'shift', 'daily_hours', 'capacity', 'status', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        Student::class => ['id', 'full_name', 'birth_date', 'ra', 'address_street', 'address_number', 'address_complement', 'address_district', 'address_city', 'address_state', 'postal_code', 'residence_zone', 'social_name', 'sex', 'race_color', 'nationality', 'birthplace_city', 'birthplace_state', 'cpf', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at', 'photo_updated_at', 'rg_number', 'rg_issuer', 'rg_state', 'rg_issued_at', 'birth_certificate', 'latitude', 'longitude', 'geo_source', 'geo_updated_at'],
        StudentEnrollment::class => ['id', 'student_id', 'school_id', 'school_year_id', 'school_class_id', 'status', 'started_at', 'ended_at', 'destination_note', 'transport_status', 'transport_route', 'transport_stop', 'transport_adapted_vehicle', 'deleted_at', 'deleted_by', 'deletion_reason', 'restored_by', 'restoration_reason', 'created_at', 'updated_at'],
        WaitlistRequest::class => ['id', 'status', 'stage', 'intake_year', 'requested_at', 'cpf', 'guardian_phone', 'priority_criteria', 'status_reason', 'deleted_at', 'student_id', 'enrollment_id', 'school_id', 'school_class_id'],
        'student_import_receipt' => ['id', 'school_id', 'school_year_id', 'student_count', 'ignored_count', 'address_completed_count'],
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
        if (in_array($connection, ['tenant', 'platform'], true) === false) {
            throw new InvalidArgumentException('Destino de auditoria não permitido.');
        }

        if ($connection === 'tenant' && app(TenantContext::class)->hasActive() === false) {
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
            // Onde a criança mora é dado delicado; a localização da escola é pública.
            if ($value !== null && $entity === Student::class && in_array($key, ['latitude', 'longitude'], true)) {
                $values[$key] = '[REDACTED]';

                continue;
            }
            // Dados sensíveis: a auditoria registra que mudou, sem guardar o valor.
            if ($value !== null && preg_match('/\A(?:password|senha|token|secret|segredo|laudo|medical|saude|saúde|diagnos(?:tico)?|cid|access_key|race_color|cpf|document|phone|institutional_email|phone_secondary|work_phone|transport_stop|transport_adapted_vehicle|guardian_phone|priority_criteria|status_reason|correction_reason|cancel_reason|revoke_reason|verification_code|blood_type|alert_text|care_instructions|rg_number|birth_certificate)\z/iu', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = '[REDACTED]';
            }
        }

        return $values;
    }
}
