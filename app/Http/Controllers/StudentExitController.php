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
        $lastExits = StudentExit::query()->whereIn('student_id', $ids)->where('school_id', $school->id)
            ->where('exited_at', '>=', today())->orderByDesc('exited_at')->get()->unique('student_id')->keyBy('student_id');

        $todayExits = StudentExit::query()->where('school_id', $school->id)->where('exited_at', '>=', today())
            ->with('student')->orderByDesc('exited_at')->limit(50)->get();

        return view('exit.index', compact('municipality', 'school', 'search', 'students', 'tooMany', 'guardians', 'authorizations', 'lastExits', 'todayExits'));
    }

    public function store(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school] = $this->school($request, $escola);
        $student = Student::query()->whereHas('enrollments', fn ($enrollments) => $enrollments->where('school_id', $school->id)->where('status', 'active'))->findOrFail($aluno);

        $person = (string) $request->validate(['pessoa' => ['required', 'string', 'regex:/\A(guardian|authorization):[0-9a-f-]{36}\z/']], [
            'pessoa.required' => 'Escolha quem está levando o aluno.',
            'pessoa.regex' => 'Escolha quem está levando o aluno.',
        ])['pessoa'];
        [$type, $id] = explode(':', $person, 2);

        if ($type === 'guardian') {
            $link = StudentGuardian::query()->where('student_id', $student->id)->where('guardian_id', $id)->with('guardian')->first();
            $name = $link?->guardian?->full_name;
            $relationship = $link === null ? null : (GuardianController::RELATIONSHIPS[$link->relationship] ?? 'Responsável');
        } else {
            $authorization = StudentPickupAuthorization::query()->where('student_id', $student->id)->find($id);
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

        return redirect()->route('school.exits.index', ['municipio' => $municipality->slug, 'escola' => $school->id])
            ->with('status', 'Saída registrada: '.$student->full_name.' entregue a '.$name.' ('.$relationship.') às '.$exit->exited_at->format('H:i').'.');
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
