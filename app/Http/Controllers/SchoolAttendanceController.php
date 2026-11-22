<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolAttendanceController extends Controller
{
    private const STATUSES = ['present', 'absent', 'justified'];

    public function index(Request $request, string $municipio, string $escola, string $turma): View
    {
        [$municipality, $school, $class] = $this->schoolClass($request, $escola, $turma);
        $validated = $request->validate(['data' => ['nullable', 'date_format:Y-m-d']], [
            'data.date_format' => 'Informe uma data válida no formato dia, mês e ano.',
        ]);
        $date = Carbon::parse($validated['data'] ?? today()->toDateString())->startOfDay();
        $this->assertNotFuture($date);

        $enrollments = $this->activeEnrollments($school, $class)
            ->with('student')
            ->get()
            ->sortBy(fn (StudentEnrollment $enrollment): string => Str::lower(Str::ascii((string) $enrollment->student?->full_name)).'|'.$enrollment->id)
            ->values();
        $students = $enrollments->pluck('student')->filter()->unique('id')->values();

        $session = AttendanceSession::query()
            ->where('school_class_id', $class->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->with('marks')
            ->first();
        $marks = $session !== null ? $session->marks->keyBy('student_id') : collect();
        $historical = $date->lt(today());
        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $canSubmit = ! $historical || ($canManage && $session !== null);
        $present = $marks->where('status', 'present')->count();
        $justified = $marks->where('status', 'justified')->count();
        $absent = $marks->where('status', 'absent')->count() + $justified;

        return view('classes.attendance', compact(
            'municipality', 'school', 'class', 'date', 'students',
            'session', 'marks', 'historical', 'canManage', 'canSubmit',
            'present', 'absent', 'justified',
        ));
    }

    public function store(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        return $this->save($request, $escola, $turma, historical: false);
    }

    public function correct(Request $request, string $municipio, string $escola, string $turma): RedirectResponse
    {
        return $this->save($request, $escola, $turma, historical: true);
    }

    private function save(Request $request, string $escola, string $turma, bool $historical): RedirectResponse
    {
        [$municipality, $school, $class] = $this->schoolClass($request, $escola, $turma);
        if ($historical) {
            Gate::authorize('manageStudents', [$school, $municipality]);
        }

        if (is_string($request->input('motivo'))) {
            $request->merge(['motivo' => trim($request->input('motivo'))]);
        }
        $validated = $request->validate([
            'data' => ['required', 'date_format:Y-m-d'],
            'presencas' => ['required', 'array', 'min:1', 'max:500'],
            'presencas.*.situacao' => ['required', Rule::in(self::STATUSES)],
            'presencas.*.observacao' => ['nullable', 'string', 'max:160'],
            'motivo' => $historical ? ['required', 'string', 'min:10', 'max:500'] : ['prohibited'],
        ], [
            'data.required' => 'Informe a data da chamada.',
            'data.date_format' => 'Informe uma data válida.',
            'presencas.required' => 'A turma precisa ter alunos ativos para registrar a chamada.',
            'presencas.min' => 'A turma precisa ter alunos ativos para registrar a chamada.',
            'presencas.max' => 'Esta chamada excede o limite de 500 alunos.',
            'presencas.*.situacao.required' => 'Marque a situação de todos os alunos.',
            'presencas.*.situacao.in' => 'Escolha Presente, Falta ou Falta justificada.',
            'presencas.*.observacao.max' => 'A observação pode ter até 160 caracteres.',
            'motivo.required' => 'Informe por que a chamada anterior precisa ser corrigida.',
            'motivo.min' => 'O motivo precisa ter pelo menos 10 caracteres.',
            'motivo.max' => 'O motivo pode ter até 500 caracteres.',
        ], ['data' => 'Data', 'presencas' => 'Presenças', 'motivo' => 'Motivo']);

        $date = Carbon::parse($validated['data'])->startOfDay();
        $this->assertNotFuture($date);
        if ($historical && ! $date->lt(today())) {
            throw ValidationException::withMessages(['data' => 'Esta opção corrige somente uma chamada de dia anterior.']);
        }
        if (! $historical && ! $date->isSameDay(today())) {
            throw ValidationException::withMessages(['data' => 'A chamada comum só pode ser registrada hoje. Para dias anteriores, use Corrigir chamada.']);
        }

        $counts = DB::connection('tenant')->transaction(function () use ($request, $school, $class, $validated, $date, $historical): array {
            // A turma serializa chamadas concorrentes e a lista é rechecada no lock.
            SchoolClass::query()
                ->where('school_id', $school->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->findOrFail($class->id);
            $activeIds = $this->activeEnrollments($school, $class)
                ->lockForUpdate()->pluck('student_id')->unique()->values()->all();
            $receivedIds = array_keys($validated['presencas']);
            sort($activeIds);
            sort($receivedIds);
            if ($activeIds === [] || $activeIds !== $receivedIds) {
                throw ValidationException::withMessages([
                    'presencas' => 'A lista de alunos mudou ou contém alguém de outra turma. Abra a chamada novamente.',
                ]);
            }

            $session = AttendanceSession::query()
                ->where('school_class_id', $class->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->lockForUpdate()
                ->first();
            if ($historical && $session === null) {
                throw ValidationException::withMessages([
                    'data' => 'Não existe chamada registrada nessa data para corrigir.',
                ]);
            }

            $created = $session === null;
            if ($created) {
                $session = AttendanceSession::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_class_id' => $class->id,
                    'attendance_date' => $date->toDateString(),
                    'recorded_by' => (string) $request->user()->getAuthIdentifier(),
                    'recorded_at' => now(),
                ]);
            }

            $existing = AttendanceMark::query()
                ->where('attendance_session_id', $session->id)
                ->lockForUpdate()
                ->get()->keyBy('student_id');
            $beforeCounts = $this->counts($existing->all());
            $changed = false;
            foreach ($validated['presencas'] as $studentId => $input) {
                $status = $input['situacao'];
                $observation = $status === 'present' ? null : (trim((string) ($input['observacao'] ?? '')) ?: null);
                $mark = $existing->get($studentId);
                if ($mark === null) {
                    $mark = AttendanceMark::query()->create([
                        'id' => (string) Str::uuid(),
                        'attendance_session_id' => $session->id,
                        'student_id' => $studentId,
                        'status' => $status,
                        'observation' => $observation,
                    ]);
                    $changed = true;
                    app(AuditTrail::class)->record('tenant', 'attendance_mark.created', AttendanceMark::class, $mark->id, null,
                        $mark->only(['id', 'attendance_session_id', 'student_id', 'status']));
                } elseif ($mark->status !== $status || $mark->observation !== $observation) {
                    $before = $mark->only(['id', 'attendance_session_id', 'student_id', 'status']);
                    $mark->update(['status' => $status, 'observation' => $observation]);
                    $changed = true;
                    app(AuditTrail::class)->record('tenant', 'attendance_mark.updated', AttendanceMark::class, $mark->id, $before,
                        $mark->only(['id', 'attendance_session_id', 'student_id', 'status']));
                }
            }

            $afterCounts = $this->counts(array_values($validated['presencas']), validatedInput: true);
            if ($created) {
                app(AuditTrail::class)->record('tenant', 'attendance_session.created', AttendanceSession::class, $session->id, null,
                    $this->sessionSnapshot($session, $afterCounts));
            } elseif ($changed || $historical) {
                $before = $this->sessionSnapshot($session, $beforeCounts);
                $session->update([
                    'last_corrected_by' => (string) $request->user()->getAuthIdentifier(),
                    'last_corrected_at' => now(),
                    'correction_reason' => $historical ? $validated['motivo'] : null,
                ]);
                app(AuditTrail::class)->record('tenant', 'attendance_session.corrected', AttendanceSession::class,
                    $session->id, $before, $this->sessionSnapshot($session, $afterCounts));
            }

            return $afterCounts;
        });

        $present = $counts['present_count'];
        $absent = $counts['absent_count'] + $counts['justified_count'];
        $justified = $counts['justified_count'];
        $summary = $present.' '.($present === 1 ? 'presente' : 'presentes').', '
            .$absent.' '.($absent === 1 ? 'falta' : 'faltas');
        if ($justified > 0) {
            $summary .= ' ('.$justified.' '.($justified === 1 ? 'justificada' : 'justificadas').')';
        }

        return redirect()->route('school.classes.attendance.index', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id,
            'data' => $date->toDateString(),
        ])->with('status', 'Chamada salva: '.$summary.'.');
    }

    private function counts(array $marks, bool $validatedInput = false): array
    {
        $counts = ['present_count' => 0, 'absent_count' => 0, 'justified_count' => 0];
        foreach ($marks as $mark) {
            $status = $validatedInput ? $mark['situacao'] : $mark->status;
            $counts[$status.'_count']++;
        }

        return $counts;
    }

    private function sessionSnapshot(AttendanceSession $session, array $counts): array
    {
        return [
            ...$session->only([
                'id', 'school_class_id', 'attendance_date', 'recorded_by', 'recorded_at',
                'last_corrected_by', 'last_corrected_at', 'correction_reason',
            ]),
            ...$counts,
        ];
    }

    private function assertNotFuture(Carbon $date): void
    {
        if ($date->gt(today())) {
            throw ValidationException::withMessages(['data' => 'Não é permitido registrar presença em data futura.']);
        }
    }

    private function activeEnrollments(School $school, SchoolClass $class): Builder
    {
        return StudentEnrollment::query()
            ->where('school_id', $school->id)
            ->where('school_year_id', $class->school_year_id)
            ->where('school_class_id', $class->id)
            ->where('status', 'active');
    }

    private function schoolClass(Request $request, string $escola, string $turma): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);
        $class = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->with('schoolYear')
            ->findOrFail($turma);

        return [$municipality, $school, $class];
    }
}
