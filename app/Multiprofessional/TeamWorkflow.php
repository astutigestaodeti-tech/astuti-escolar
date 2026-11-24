<?php

namespace App\Multiprofessional;

use App\Audit\AuditTrail;
use App\Models\Tenant\MultiprofessionalRequest;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Str;

class TeamWorkflow
{
    /** A matrícula deve estar bloqueada na transação do chamador. */
    public function open(StudentEnrollment $entry, string $area, string $reason, string $actor, ?string $searchCase = null): MultiprofessionalRequest
    {
        if ($searchCase !== null) {
            $existing = MultiprofessionalRequest::query()->where('active_search_case_id', $searchCase)->first();
            if ($existing !== null) {
                return $existing;
            }
        }
        $existing = MultiprofessionalRequest::query()->where('enrollment_id', $entry->id)
            ->where('area', $area)->where('status', '!=', 'closed')->first();
        if ($existing !== null) {
            if ($searchCase !== null && $existing->active_search_case_id === null) {
                $existing->active_search_case_id = $searchCase;
                $existing->save();
                app(AuditTrail::class)->record('tenant', 'multiprofessional.request.linked', MultiprofessionalRequest::class, $existing->id);
            }

            return $existing;
        }

        $request = MultiprofessionalRequest::query()->create([
            'id' => (string) Str::uuid(), 'enrollment_id' => $entry->id,
            'school_id' => $entry->school_id, 'student_id' => $entry->student_id,
            'school_year_id' => $entry->school_year_id, 'active_search_case_id' => $searchCase,
            'area' => $area, 'reason_category' => $reason, 'status' => 'open',
            'requested_by' => $actor, 'opened_at' => now(),
        ]);
        app(AuditTrail::class)->record('tenant', 'multiprofessional.request.opened', MultiprofessionalRequest::class, $request->id);

        return $request;
    }
}
