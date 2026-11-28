<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentExit;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Students\StudentSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saída de alunos (portão): acha o aluno, mostra a foto dele e de quem pode
 * buscar e registra a quem foi entregue. Quem consulta a escola usa a tela,
 * inclusive portaria e professores; nada aqui altera o cadastro.
 */
class StudentExitController extends Controller
{
    private const RESULTS = 8;

    public function index(Request $request, string $municipio, string $escola): View
    {
        [$municipality, $school] = $this->school($request, $escola);
        $validated = $request->validate(['busca' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($validated['busca'] ?? ''));

        $students = collect();
        if (mb_strlen($search) >= 2) {
            $query = Student::query()->whereHas('enrollments', fn ($enrollments) => $enrollments->where('school_id', $school->id)->where('status', 'active'));
            StudentSearch::apply($query, $search);
            $students = $query->with(['enrollments' => fn ($enrollments) => $enrollments->where('school_id', $school->id)->where('status', 'active')->with('schoolClass')])
                ->orderBy('full_name')->limit(self::RESULTS + 1)->get();
        }
        $tooMany = $students->count() > self::RESULTS;
        $students = $students->take(self::RESULTS);

        $ids = $students->pluck('id');
        $guardians = StudentGuardian::query()->whereIn('student_id', $ids)->with('guardian')
            ->orderByDesc('is_primary_contact')->get()->groupBy('student_id');
        $authorizations = StudentPickupAuthorization::query()->whereIn('student_id', $ids)
            ->where(fn ($valid) => $valid->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->orderBy('full_name')->get()->groupBy('student_id');
        $lastExits = StudentExit::query()->valid()->whereIn('student_id', $ids)->where('school_id', $school->id)
            ->where('exited_at', '>=', today())->orderByDesc('exited_at')->get()->unique('student_id')->keyBy('student_id');

        $todayExits = StudentExit::query()->where('school_id', $school->id)->where('exited_at', '>=', today())
            ->with('student')->orderByDesc('exited_at')->limit(50)->get();

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $userId = $request->user()?->getAuthIdentifier();
        $undoable = $todayExits->filter(fn (StudentExit $exit): bool => $exit->canBeUndoneBy($userId, $canManage))->pluck('id')->flip();

        $validExitCount = $todayExits->whereNull('cancelled_at')->count();

        return view('exit.index', compact('municipality', 'school', 'search', 'students', 'tooMany', 'guardians', 'authorizations', 'lastExits', 'todayExits', 'undoable', 'validExitCount'));
    }

    public function store(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        $person = (string) $request->validate(['pessoa' => ['required', 'string', 'regex:/\A(guardian|authorization):[0-9a-f-]{36}\z/']], [
            'pessoa.required' => 'Escolha quem está levando o aluno.',
            'pessoa.regex' => 'Escolha quem está levando o aluno.',
        ])['pessoa'];
        [$type, $id] = explode(':', $person, 2);
        if (! Str::isUuid($id)) {
            throw ValidationException::withMessages(['pessoa' => 'Escolha quem está levando o aluno.']);
        }

        // Mesma ordem de locks do cadastro de responsáveis e autorizações:
        // primeiro o aluno, depois o vínculo. A saída e a auditoria são atômicas.
        [$student, $exit] = DB::connection('tenant')->transaction(function () use ($request, $school, $aluno, $type, $id): array {
            $student = Student::query()->whereHas('enrollments', fn ($enrollments) => $enrollments->where('school_id', $school->id)->where('status', 'active'))
                ->lockForUpdate()->findOrFail($aluno);

            if ($type === 'guardian') {
                $link = StudentGuardian::query()->where('student_id', $student->id)->where('guardian_id', $id)->with('guardian')->lockForUpdate()->first();
                $name = $link?->guardian?->full_name;
                $relationship = $link === null ? null : (GuardianController::RELATIONSHIPS[$link->relationship] ?? 'Responsável');
            } else {
                $authorization = StudentPickupAuthorization::query()->where('student_id', $student->id)->lockForUpdate()->find($id);
                $name = $authorization !== null && ! $authorization->isExpired() ? $authorization->full_name : null;
                $relationship = $authorization?->relationship ?: 'Pessoa autorizada';
            }
            if ($name === null) {
                throw ValidationException::withMessages(['pessoa' => 'Esta pessoa não está autorizada a buscar o aluno. Não entregue e procure a secretaria.']);
            }

            $exit = StudentExit::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => $school->id,
                'person_type' => $type,
                'person_id' => $id,
                'person_name' => $name,
                'person_relationship' => $relationship,
                'recorded_by' => $request->user()?->getAuthIdentifier(),
                'exited_at' => now(),
            ]);
            app(AuditTrail::class)->record('tenant', 'student_exit.recorded', StudentExit::class, $exit->id, null, $exit->only(['id', 'student_id', 'school_id', 'person_type', 'person_id', 'person_name', 'person_relationship', 'exited_at']));

            return [$student, $exit];
        });

        return redirect()->route('school.exits.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Saída registrada: '.$student->full_name.' entregue a '.$exit->person_name.' ('.$exit->person_relationship.') às '.$exit->exited_at->format('H:i').'. Registrou por engano? Desfaça em até '.StudentExit::UNDO_MINUTES.' minutos em "Saídas de hoje".');
    }

    /**
     * Desfaz uma saída registrada por engano. O registro fica guardado como
     * desfeito, com motivo; o aluno volta a constar como presente na escola.
     */
    public function cancel(Request $request, string $municipio, string $escola, string $saida): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        $reason = trim((string) $request->validate(['motivo' => ['required', 'string', 'min:10', 'max:300']], [
            'motivo.required' => 'Conte em poucas palavras por que a saída está sendo desfeita.',
            'motivo.min' => 'Escreva o motivo com pelo menos 10 caracteres.',
            'motivo.max' => 'O motivo pode ter até 300 caracteres.',
        ])['motivo']);

        $canManage = Gate::allows('manageStudents', [$school, $municipality]);
        $userId = $request->user()?->getAuthIdentifier();

        $exit = DB::connection('tenant')->transaction(function () use ($school, $saida, $userId, $canManage, $reason): StudentExit {
            $exit = StudentExit::query()->where('school_id', $school->id)->lockForUpdate()->findOrFail($saida);
            if ($exit->isCancelled()) {
                throw ValidationException::withMessages(['motivo' => 'Esta saída já foi desfeita.']);
            }
            if (! $exit->canBeUndoneBy($userId, $canManage)) {
                throw ValidationException::withMessages(['motivo' => 'Passou o prazo de '.StudentExit::UNDO_MINUTES.' minutos para desfazer. Peça à secretaria ou à direção.']);
            }

            $before = $exit->only(['id', 'cancelled_at', 'cancelled_by']);
            $exit->forceFill(['cancelled_at' => now(), 'cancelled_by' => $userId, 'cancel_reason' => $reason])->save();
            app(AuditTrail::class)->record('tenant', 'student_exit.cancelled', StudentExit::class, $exit->id, $before, $exit->only(['id', 'student_id', 'school_id', 'cancelled_at', 'cancelled_by', 'cancel_reason']));

            return $exit;
        });

        $student = Student::query()->find($exit->student_id);

        return redirect()->route('school.exits.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Saída desfeita: '.$student?->full_name.' volta a constar como na escola. O registro das '.$exit->exited_at->format('H:i').' fica guardado como desfeito.');
    }

    /** @return array{0: mixed, 1: School} */
    private function school(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('view', [$school, $municipality]);

        return [$municipality, $school];
    }
}
