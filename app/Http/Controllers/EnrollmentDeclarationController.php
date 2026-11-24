<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Documents\SchoolDeclarationSnapshot;
use App\Documents\VerificationCode;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Support\Qr\VerificationQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class EnrollmentDeclarationController extends Controller
{
    public function create(Request $request, string $municipio, string $escola, string $aluno): Response
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $kind = $request->query('tipo', 'enrollment_declaration');
        abort_unless(is_string($kind) && isset(IssuedDocument::DECLARATIONS[$kind]), 404);
        $enrollments = $student->enrollments()->where('school_id', $school->id)
            ->whereIn('status', $this->statuses($kind))
            ->with(['schoolYear', 'schoolClass'])->orderByDesc('created_at')->get();
        $selected = $request->query('matricula');
        if ($kind === 'completion_declaration') {
            $confirmed = StudentFinalResult::query()->whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->whereIn('result', ['aprovado', 'acompanhado'])->whereNotNull('confirmed_at')->get()->keyBy('student_enrollment_id');
            $closed = ClassYearClosure::query()->whereIn('school_class_id', $enrollments->pluck('school_class_id')->filter())
                ->whereNotNull('closed_at')->where('closed_at', '<=', now())->pluck('school_class_id');
            $enrollments = $enrollments->filter(fn (StudentEnrollment $entry): bool => $closed->contains($entry->school_class_id)
                && $confirmed->get($entry->id)?->school_class_id === $entry->school_class_id)->values();
        }
        $hasActiveEnrollment = $student->enrollments()->where('school_id', $school->id)->where('status', 'active')->exists();
        $documents = IssuedDocument::query()->where('school_id', $school->id)->where('student_id', $student->id)
            ->where('kind', $kind)->orderByDesc('issued_at')->get();

        return response()->view('documents.create', compact('municipality', 'school', 'student', 'enrollments', 'kind', 'selected', 'documents', 'hasActiveEnrollment'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $validated = $request->validate([
            'kind' => ['nullable', Rule::in(array_keys(IssuedDocument::DECLARATIONS))],
            'matricula' => ['required', 'uuid'],
            'purpose' => ['nullable', 'string', 'max:120'],
            'inicio' => ['required_if:kind,attendance_declaration', 'nullable', 'date_format:Y-m-d'],
            'fim' => ['required_if:kind,attendance_declaration', 'nullable', 'date_format:Y-m-d'],
        ]);
        $kind = $validated['kind'] ?? 'enrollment_declaration';
        $purpose = $kind === 'enrollment_declaration' ? (trim((string) ($validated['purpose'] ?? '')) ?: null) : null;

        $document = DB::connection('tenant')->transaction(function () use ($school, $student, $municipality, $validated, $purpose, $kind, $request): IssuedDocument {
            // Ordem compatível com o fechamento e a renovação de turmas.
            if ($kind === 'completion_declaration') {
                $classId = StudentEnrollment::query()->where('school_id', $school->id)
                    ->where('student_id', $student->id)->whereIn('status', $this->statuses($kind))->findOrFail($validated['matricula'])->school_class_id;
                if ($classId !== null) {
                    ClassYearClosure::query()->whereKey($classId)->lockForUpdate()->first();
                }
            }
            $enrollment = StudentEnrollment::query()->where('school_id', $school->id)
                ->where('student_id', $student->id)->whereIn('status', $this->statuses($kind))
                ->with(['schoolClass', 'schoolYear'])->lockForUpdate()->findOrFail($validated['matricula']);
            if ($kind === 'completion_declaration' && $enrollment->school_class_id !== $classId) {
                throw ValidationException::withMessages(['matricula' => 'A turma da matrícula mudou. Consulte novamente.']);
            }
            $class = $enrollment->schoolClass;
            $snapshot = $kind === 'enrollment_declaration' ? null : app(SchoolDeclarationSnapshot::class)->build($kind, $enrollment, $school, $validated);

            for ($attempt = 0; $attempt < 12; $attempt++) {
                $code = VerificationCode::generate();
                if (IssuedDocument::query()->where('verification_code', $code)->exists()) {
                    continue;
                }

                // UNIQUE no PostgreSQL é a proteção definitiva contra colisões simultâneas.
                $document = IssuedDocument::query()->create([
                    'id' => (string) Str::uuid(), 'kind' => $kind,
                    'student_id' => $student->id, 'school_id' => $school->id, 'enrollment_id' => $enrollment->id,
                    'verification_code' => $code, 'issued_by' => $request->user()->id, 'issued_at' => now(),
                    'purpose' => $purpose,
                    'declaration_snapshot' => $snapshot,
                    'snapshot_student_name' => $student->full_name,
                    'snapshot_school_name' => $school->name,
                    'snapshot_municipality_name' => $municipality->legal_name,
                    'snapshot_school_year' => $enrollment->schoolYear->year,
                    'snapshot_stage' => $class?->stage, 'snapshot_class_name' => $class?->name,
                    'snapshot_shift' => $class?->shift,
                ]);
                app(AuditTrail::class)->record('tenant', $kind.'.issued', IssuedDocument::class, $document->id,
                    null, $document->only(['id', 'student_id', 'school_id', 'enrollment_id', 'issued_by', 'issued_at', 'verification_code']));

                return $document;
            }

            throw new RuntimeException('Não foi possível gerar um código único. Tente novamente.');
        });

        return redirect()->route('school.students.declarations.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'documento' => $document->id,
        ])->with('status', 'Declaração emitida com código de verificação e auditoria.');
    }

    public function show(Request $request, string $municipio, string $escola, string $aluno, string $documento, VerificationQrCode $qr): Response
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $document = $this->document($school, $student, $documento);
        $verificationUrl = route('documents.verify', ['codigo' => $document->verification_code]);
        $qrSvg = $qr->svg($verificationUrl);
        if ($document->kind !== 'enrollment_declaration') {
            app(AuditTrail::class)->record('tenant', $document->kind.'.viewed', IssuedDocument::class, $document->id);
        }

        return response()->view('documents.show', compact('municipality', 'school', 'student', 'document', 'verificationUrl', 'qrSvg'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function revoke(Request $request, string $municipio, string $escola, string $aluno, string $documento): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $reason = trim((string) $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:500']])['motivo']);

        DB::connection('tenant')->transaction(function () use ($school, $student, $documento, $request, $reason): void {
            $document = IssuedDocument::query()->whereIn('kind', array_keys(IssuedDocument::DECLARATIONS))->where('school_id', $school->id)
                ->where('student_id', $student->id)->lockForUpdate()->findOrFail($documento);
            if ($document->revoked_at !== null) {
                throw ValidationException::withMessages(['motivo' => 'Esta declaração já foi revogada.']);
            }

            $document->forceFill(['revoked_at' => now(), 'revoked_by' => $request->user()->id, 'revoke_reason' => $reason])->save();
            app(AuditTrail::class)->record('tenant', $document->kind.'.revoked', IssuedDocument::class, $document->id,
                ['id' => $document->id, 'verification_code' => $document->verification_code, 'revoked_at' => null],
                $document->only(['id', 'school_id', 'student_id', 'verification_code', 'revoked_at', 'revoked_by', 'revoke_reason']));
        });

        return redirect()->route('school.students.declarations.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'documento' => $documento,
        ])->with('status', 'Declaração revogada. O código público agora informa que ela não é válida.');
    }

    private function statuses(string $kind): array
    {
        return match ($kind) {
            'attendance_declaration' => ['active', 'transferred', 'left_network', 'completed'],
            'transfer_declaration' => ['transferred', 'left_network'],
            'completion_declaration' => ['active', 'completed'],
            default => ['active'],
        };
    }

    private function authorizeStudent(Request $request, string $schoolId, string $studentId): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('manageStudents', [$school, $municipality]);
        $student = Student::query()->whereHas('enrollments', fn ($q) => $q->where('school_id', $school->id))
            ->findOrFail($studentId);

        return [$municipality, $school, $student];
    }

    private function document(School $school, Student $student, string $documentId): IssuedDocument
    {
        return IssuedDocument::query()->whereIn('kind', array_keys(IssuedDocument::DECLARATIONS))->where('school_id', $school->id)
            ->where('student_id', $student->id)->findOrFail($documentId);
    }
}
