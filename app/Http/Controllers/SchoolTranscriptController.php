<?php

namespace App\Http\Controllers;

use App\Academics\SchoolTranscriptBuilder;
use App\Audit\AuditTrail;
use App\Documents\TranscriptFingerprint;
use App\Documents\VerificationCode;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentPriorSchoolYear;
use App\Support\Qr\VerificationQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SchoolTranscriptController extends Controller
{
    public function index(
        Request $request, string $municipio, string $escola, string $aluno,
        SchoolTranscriptBuilder $builder,
    ): Response {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $data = $builder->forStudent($student, $municipality);
        $documents = IssuedDocument::query()->where('kind', 'school_transcript')
            ->where('student_id', $student->id)->where('school_id', $school->id)
            ->orderByDesc('issued_at')->get();

        return response()->view('transcripts.index', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'data' => $data,
            'documents' => $documents,
            'canIssue' => $this->canWrite($request, $municipality->id, $school->id),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function savePrevious(
        Request $request, string $municipio, string $escola, string $aluno,
    ): RedirectResponse {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        abort_unless($this->canWrite($request, $municipality->id, $school->id), 403);
        $input = $request->validate([
            'record' => ['nullable', 'uuid'],
            'year' => ['required', 'integer', 'between:1900,2200'],
            'stage' => ['required', 'string', 'min:2', 'max:120'],
            'school' => ['required', 'string', 'min:2', 'max:180'],
            'city' => ['required', 'string', 'min:2', 'max:120'],
            'state' => ['required', 'string', 'size:2', 'regex:/\A[A-Za-z]{2}\z/D'],
            'result' => ['required', 'string', 'min:2', 'max:100'],
            'frequency' => ['nullable', 'string', 'regex:/\A(?:100(?:[,.]0)?|[0-9]{1,2}(?:[,.][0-9])?)\z/D'],
            'grades' => ['nullable', 'array', 'max:20'],
            'grades.*.name' => ['nullable', 'string', 'max:120'],
            'grades.*.value' => ['nullable', 'string', 'max:50'],
        ]);
        $grades = [];
        foreach ($input['grades'] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name === '' && $value === '') {
                continue;
            }
            if ($name === '' || $value === '') {
                throw ValidationException::withMessages(['grades' => 'Informe componente e nota ou conceito, ou deixe ambos em branco.']);
            }
            if (isset($grades[$name])) {
                throw ValidationException::withMessages(['grades' => 'Cada componente deve aparecer apenas uma vez.']);
            }
            $grades[$name] = $value;
        }

        $year = (int) $input['year'];
        DB::connection('tenant')->transaction(function () use ($request, $student, $input, $grades, $year): void {
            $record = isset($input['record']) ? StudentPriorSchoolYear::query()
                ->where('student_id', $student->id)->lockForUpdate()->findOrFail($input['record']) : null;

            $duplicate = StudentPriorSchoolYear::query()->where('student_id', $student->id)
                ->where('school_year', $year)
                ->when($record !== null, fn ($q) => $q->where('id', '<>', $record->id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['year' => 'Já existe ano anterior com esse ano. Edite o registro existente em vez de cadastrar outro.']);
            }

            $values = [
                'school_year' => $year,
                'school_stage' => trim($input['stage']),
                'school_name' => trim($input['school']),
                'city' => trim($input['city']),
                'state' => strtoupper($input['state']),
                'result' => trim($input['result']),
                'attendance_percent' => isset($input['frequency']) && $input['frequency'] !== ''
                    ? number_format((float) str_replace(',', '.', $input['frequency']), 1, '.', '') : null,
                'component_grades' => $grades,
                'updated_by' => $request->user()->id,
            ];
            $before = $record?->only(['id', 'student_id', 'school_year', 'school_stage', 'result', 'attendance_percent']);

            if ($record === null) {
                $record = StudentPriorSchoolYear::query()->create([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'recorded_by' => $request->user()->id,
                    ...$values,
                ]);
            } else {
                $record->fill($values)->save();
            }

            app(AuditTrail::class)->record('tenant',
                $before === null ? 'school_transcript.prior.created' : 'school_transcript.prior.updated',
                StudentPriorSchoolYear::class, $record->id,
                $before, [
                    ...$record->only(['id', 'student_id', 'school_year', 'school_stage', 'result', 'attendance_percent']),
                    'grades_changed' => true,
                ]);
        });

        return redirect()->route('school.students.transcripts.index', $this->params($municipality->slug, $school->id, $student->id))
            ->with('status', 'Ano anterior registrado e auditado.');
    }

    public function archivePrevious(
        Request $request, string $municipio, string $escola, string $aluno, string $registro,
    ): RedirectResponse {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        abort_unless($this->canWrite($request, $municipality->id, $school->id), 403);
        $reason = trim($request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']])['reason']);

        DB::connection('tenant')->transaction(function () use ($request, $student, $registro, $reason): void {
            $prior = StudentPriorSchoolYear::query()->where('student_id', $student->id)
                ->lockForUpdate()->findOrFail($registro);
            $prior->forceFill([
                'deleted_at' => now(),
                'deleted_by' => $request->user()->id,
                'deletion_reason' => $reason,
            ])->save();
            app(AuditTrail::class)->record('tenant', 'school_transcript.prior.archived',
                StudentPriorSchoolYear::class, $prior->id,
                ['school_year' => $prior->school_year, 'deleted_at' => null],
                $prior->only(['id', 'student_id', 'school_year', 'deleted_at', 'deleted_by']));
        });

        return redirect()->route('school.students.transcripts.index', $this->params($municipality->slug, $school->id, $student->id))
            ->with('status', 'Ano anterior arquivado com justificativa. O histórico emitido anteriormente permanece inalterado.');
    }

    public function issue(
        Request $request, string $municipio, string $escola, string $aluno,
        SchoolTranscriptBuilder $builder,
    ): RedirectResponse {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        abort_unless($this->canWrite($request, $municipality->id, $school->id), 403);
        $input = $request->validate(['observations' => ['nullable', 'string', 'max:2000']]);

        $document = DB::connection('tenant')->transaction(function () use (
            $request, $student, $school, $municipality, $builder, $input,
        ): IssuedDocument {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $enrollment = StudentEnrollment::query()->where('student_id', $student->id)
                ->where('school_id', $school->id)->with(['schoolClass', 'schoolYear'])
                ->get()->filter(fn (StudentEnrollment $e): bool => $e->schoolClass !== null
                    && str_contains(mb_strtolower($e->schoolClass->stage), 'fundamental'))
                ->sortByDesc(fn (StudentEnrollment $e): int => (int) $e->schoolYear->year)
                ->first();
            if ($enrollment === null) {
                throw ValidationException::withMessages(['observations' => 'O aluno não tem matrícula no Ensino Fundamental desta escola.']);
            }

            $history = $builder->forStudent($student, $municipality);
            if ($history['years'] === []) {
                throw ValidationException::withMessages(['observations' => 'Não há anos concluídos ou anteriores para emitir o histórico.']);
            }

            $snapshot = [
                'header' => [
                    'municipality' => $municipality->legal_name,
                    'issuer_school' => $school->name,
                    'issuer_inep' => $school->inep_code,
                    'student' => $student->full_name,
                    'birth_date' => $student->birth_date?->format('d/m/Y'),
                    'birthplace' => $student->birthplace_city,
                    'birth_state' => $student->birthplace_state,
                    'ra' => $student->ra,
                    // O schema atual não registra INEP do aluno: nunca inventar.
                    'student_inep' => $student->inep_code ?? null,
                ],
                'years' => $history['years'],
                'in_progress' => $history['inProgress'],
                'observations' => trim((string) ($input['observations'] ?? '')),
            ];
            $fingerprint = TranscriptFingerprint::fromSnapshot($snapshot);

            for ($attempt = 0; $attempt < 12; $attempt++) {
                $code = VerificationCode::generate();
                if (IssuedDocument::query()->where('verification_code', $code)->exists()) {
                    continue;
                }

                $document = IssuedDocument::query()->create([
                    'id' => (string) Str::uuid(),
                    'kind' => 'school_transcript',
                    'student_id' => $student->id,
                    'school_id' => $school->id,
                    'enrollment_id' => $enrollment->id,
                    'verification_code' => $code,
                    'issued_by' => $request->user()->id,
                    'issued_at' => now(),
                    'snapshot_student_name' => $student->full_name,
                    'snapshot_school_name' => $school->name,
                    'snapshot_municipality_name' => $municipality->legal_name,
                    'snapshot_school_year' => (int) $enrollment->schoolYear->year,
                    'snapshot_stage' => 'Ensino Fundamental',
                    'transcript_snapshot' => $snapshot,
                    'transcript_sha256' => $fingerprint,
                ]);
                app(AuditTrail::class)->record('tenant', 'school_transcript.issued',
                    IssuedDocument::class, $document->id, null,
                    $document->only(['id', 'student_id', 'school_id', 'enrollment_id', 'issued_at', 'verification_code']));

                return $document;
            }

            throw new RuntimeException('Não foi possível gerar código único para o histórico escolar.');
        });

        return redirect()->route('school.students.transcripts.show', [
            ...$this->params($municipality->slug, $school->id, $student->id), 'documento' => $document->id,
        ])->with('status', 'Histórico emitido com snapshot imutável e código de verificação.');
    }

    public function show(
        Request $request, string $municipio, string $escola, string $aluno, string $documento,
        VerificationQrCode $qr,
    ): Response {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        $document = IssuedDocument::query()->where('kind', 'school_transcript')
            ->where('school_id', $school->id)->where('student_id', $student->id)
            ->findOrFail($documento);
        $verificationUrl = route('documents.verify', ['codigo' => $document->verification_code]);
        $qrSvg = $qr->svg($verificationUrl);
        abort_unless(hash_equals($document->transcript_sha256, TranscriptFingerprint::fromSnapshot($document->transcript_snapshot)), 500);
        $canRevoke = $this->canWrite($request, $municipality->id, $school->id);

        return response()->view('transcripts.show', compact(
            'municipality', 'school', 'student', 'document', 'verificationUrl', 'qrSvg', 'canRevoke',
        ))->header('Cache-Control', 'private, no-store');
    }

    public function revoke(
        Request $request, string $municipio, string $escola, string $aluno, string $documento,
    ): RedirectResponse {
        [$municipality, $school, $student] = $this->authorizedStudent($request, $escola, $aluno);
        abort_unless($this->canWrite($request, $municipality->id, $school->id), 403);
        $reason = trim($request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']])['reason']);

        DB::connection('tenant')->transaction(function () use ($request, $school, $student, $documento, $reason): void {
            $document = IssuedDocument::query()->where('kind', 'school_transcript')
                ->where('school_id', $school->id)->where('student_id', $student->id)
                ->lockForUpdate()->findOrFail($documento);
            if ($document->revoked_at !== null) {
                throw ValidationException::withMessages(['reason' => 'Este histórico já foi revogado.']);
            }
            $document->forceFill([
                'revoked_at' => now(), 'revoked_by' => $request->user()->id,
                'revoke_reason' => $reason,
            ])->save();
            app(AuditTrail::class)->record('tenant', 'school_transcript.revoked',
                IssuedDocument::class, $document->id,
                ['revoked_at' => null],
                $document->only(['id', 'revoked_at', 'revoked_by', 'revoke_reason']));
        });

        return redirect()->route('school.students.transcripts.show', [
            ...$this->params($municipality->slug, $school->id, $student->id), 'documento' => $documento,
        ])->with('status', 'Histórico revogado. O código de verificação continuará indicando a revogação.');
    }

    private function authorizedStudent(Request $request, string $schoolId, string $studentId): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('manageStudents', [$school, $municipality]);
        $student = Student::query()->whereHas('enrollments', fn ($q) => $q->where('school_id', $school->id))
            ->findOrFail($studentId);

        return [$municipality, $school, $student];
    }

    private function canWrite(Request $request, string $municipalityId, string $schoolId): bool
    {
        if ($request->user()?->status !== 'active') {
            return false;
        }

        // Apenas a escola emite/edita. Gestor municipal consulta sem alterar.
        return DB::connection('platform')->table('memberships as membership')
            ->join('school_scopes as scope', 'scope.membership_id', '=', 'membership.id')
            ->where('membership.user_id', $request->user()->id)
            ->where('membership.municipality_id', $municipalityId)
            ->where('membership.status', 'active')
            ->whereIn('membership.role', ['school_secretary', 'school_director'])
            ->where('scope.school_id', $schoolId)->exists();
    }

    private function params(string $municipio, string $escola, string $aluno): array
    {
        return compact('municipio', 'escola', 'aluno');
    }
}
