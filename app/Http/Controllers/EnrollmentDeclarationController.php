<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Documents\VerificationCode;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Support\Qr\VerificationQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class EnrollmentDeclarationController extends Controller
{
    public function create(Request $request, string $municipio, string $escola, string $aluno): View
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $enrollments = $student->enrollments()->where('school_id', $school->id)->where('status', 'active')
            ->with(['schoolYear', 'schoolClass'])->orderByDesc('created_at')->get();

        return view('documents.create', compact('municipality', 'school', 'student', 'enrollments'));
    }

    public function store(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $validated = $request->validate(['matricula' => ['required', 'uuid'], 'purpose' => ['nullable', 'string', 'max:120']]);
        $purpose = trim((string) ($validated['purpose'] ?? '')) ?: null;

        $document = DB::connection('tenant')->transaction(function () use ($school, $student, $municipality, $validated, $purpose, $request): IssuedDocument {
            $enrollment = StudentEnrollment::query()->where('school_id', $school->id)
                ->where('student_id', $student->id)->where('status', 'active')
                ->with(['schoolClass', 'schoolYear'])->lockForUpdate()->findOrFail($validated['matricula']);
            $class = $enrollment->schoolClass;

            for ($attempt = 0; $attempt < 12; $attempt++) {
                $code = VerificationCode::generate();
                if (IssuedDocument::query()->where('verification_code', $code)->exists()) {
                    continue;
                }

                // UNIQUE no PostgreSQL é a proteção definitiva contra colisões simultâneas.
                $document = IssuedDocument::query()->create([
                    'id' => (string) Str::uuid(), 'kind' => 'enrollment_declaration',
                    'student_id' => $student->id, 'school_id' => $school->id, 'enrollment_id' => $enrollment->id,
                    'verification_code' => $code, 'issued_by' => $request->user()->id, 'issued_at' => now(),
                    'purpose' => $purpose,
                    'snapshot_student_name' => $student->full_name,
                    'snapshot_school_name' => $school->name,
                    'snapshot_municipality_name' => $municipality->legal_name,
                    'snapshot_school_year' => $enrollment->schoolYear->year,
                    'snapshot_stage' => $class?->stage, 'snapshot_class_name' => $class?->name,
                    'snapshot_shift' => $class?->shift,
                ]);
                app(AuditTrail::class)->record('tenant', 'enrollment_declaration.issued', IssuedDocument::class, $document->id,
                    null, $document->only(['id', 'student_id', 'school_id', 'enrollment_id', 'issued_by', 'issued_at', 'verification_code']));

                return $document;
            }

            throw new RuntimeException('Não foi possível gerar um código único. Tente novamente.');
        });

        return redirect()->route('school.students.declarations.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'documento' => $document->id,
        ])->with('status', 'Declaração emitida com código de verificação e auditoria.');
    }

    public function show(Request $request, string $municipio, string $escola, string $aluno, string $documento, VerificationQrCode $qr): View
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $document = $this->document($school, $student, $documento);
        $verificationUrl = route('documents.verify', ['codigo' => $document->verification_code]);
        $qrSvg = $qr->svg($verificationUrl);

        return view('documents.show', compact('municipality', 'school', 'student', 'document', 'verificationUrl', 'qrSvg'));
    }

    public function revoke(Request $request, string $municipio, string $escola, string $aluno, string $documento): RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizeStudent($request, $escola, $aluno);
        $reason = trim((string) $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:500']])['motivo']);

        DB::connection('tenant')->transaction(function () use ($school, $student, $documento, $request, $reason): void {
            $document = IssuedDocument::query()->where('kind', 'enrollment_declaration')->where('school_id', $school->id)
                ->where('student_id', $student->id)->lockForUpdate()->findOrFail($documento);
            if ($document->revoked_at !== null) {
                throw ValidationException::withMessages(['motivo' => 'Esta declaração já foi revogada.']);
            }

            $document->forceFill(['revoked_at' => now(), 'revoked_by' => $request->user()->id, 'revoke_reason' => $reason])->save();
            app(AuditTrail::class)->record('tenant', 'enrollment_declaration.revoked', IssuedDocument::class, $document->id,
                ['id' => $document->id, 'verification_code' => $document->verification_code, 'revoked_at' => null],
                $document->only(['id', 'school_id', 'student_id', 'verification_code', 'revoked_at', 'revoked_by', 'revoke_reason']));
        });

        return redirect()->route('school.students.declarations.show', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id, 'documento' => $documento,
        ])->with('status', 'Declaração revogada. O código público agora informa que ela não é válida.');
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
        return IssuedDocument::query()->where('kind', 'enrollment_declaration')->where('school_id', $school->id)
            ->where('student_id', $student->id)->findOrFail($documentId);
    }
}
