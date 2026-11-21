<?php

namespace App\Http\Controllers;

use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\TwoFactorSession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inclusão e alteração manual de alunos pela secretaria da escola.
 *
 * Gravações exigem permissão de gestão na escola e o segundo fator confirmado
 * (middleware nas rotas POST/PUT). Toda gravação é auditada pelos eventos do
 * modelo, na mesma transação: sem auditoria, nada é gravado.
 */
class StudentRecordController extends Controller
{
    private const NAME_RULES = ['required', 'string', 'min:5', 'max:180', 'regex:/\A\p{L}[\p{L}\p{M}\' .-]*\z/u'];

    private const BIRTH_RULES = ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:today'];

    public function create(Request $request, string $municipio, string $escola): View|RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        if ($redirect = $this->requireTwoFactor($request)) {
            return $redirect;
        }

        return view('students.form', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => null,
            'years' => $this->openYears(),
        ]);
    }

    public function store(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);

        $request->merge(['full_name' => $this->normalizeName($request->input('full_name'))]);
        $data = $request->validate([
            'full_name' => self::NAME_RULES,
            'birth_date' => self::BIRTH_RULES,
            'school_year_id' => ['required', 'uuid'],
            'started_at' => ['nullable', 'date_format:Y-m-d'],
            'confirmar_duplicidade' => ['nullable', 'boolean'],
        ]);

        $year = SchoolYear::query()->where('status', '!=', 'completed')->find($data['school_year_id']);
        if ($year === null) {
            throw ValidationException::withMessages(['school_year_id' => 'Escolha um ano letivo em andamento ou planejado.']);
        }

        if (! empty($data['started_at'])
            && ($data['started_at'] < $year->start_date->format('Y-m-d') || $data['started_at'] > $year->end_date->format('Y-m-d'))) {
            throw ValidationException::withMessages(['started_at' => 'A data de início precisa estar dentro do ano letivo escolhido.']);
        }

        // Nome e nascimento iguais podem ser a mesma criança: nunca fundir
        // automaticamente, mas exigir que a pessoa confirme conscientemente.
        if (! $request->boolean('confirmar_duplicidade') && $this->possibleDuplicate($data['full_name'], $data['birth_date'] ?? null)) {
            return back()->withInput()->with('possivel_duplicidade', true)->withErrors([
                'full_name' => 'Já existe no município um aluno com o mesmo nome'.(empty($data['birth_date']) ? '' : ' e a mesma data de nascimento')
                    .'. Confira antes de continuar. Se for outra pessoa, marque a confirmação e salve de novo.',
            ]);
        }

        $student = DB::connection('tenant')->transaction(function () use ($data, $school, $year): Student {
            $student = Student::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => $data['full_name'],
                'birth_date' => $data['birth_date'] ?? null,
            ]);

            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => $school->id,
                'school_year_id' => $year->id,
                'status' => 'active',
                'started_at' => $data['started_at'] ?? null,
            ]);

            return $student;
        });

        return redirect()
            ->route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $student->id])
            ->with('status', 'Aluno cadastrado e matriculado nesta escola.');
    }

    public function edit(Request $request, string $municipio, string $escola, string $aluno): View|RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);
        $student = $this->activeStudentOf($school)->findOrFail($aluno);

        if ($redirect = $this->requireTwoFactor($request)) {
            return $redirect;
        }

        return view('students.form', [
            'municipality' => $municipality,
            'school' => $school,
            'student' => $student,
            'years' => collect(),
        ]);
    }

    public function update(Request $request, string $municipio, string $escola, string $aluno): RedirectResponse
    {
        [$municipality, $school] = $this->authorizeSchool($request, $escola);
        $this->activeStudentOf($school)->findOrFail($aluno);

        $request->merge(['full_name' => $this->normalizeName($request->input('full_name'))]);
        $data = $request->validate([
            'full_name' => self::NAME_RULES,
            'birth_date' => self::BIRTH_RULES,
            'versao' => ['required', 'string', 'max:40'],
        ]);

        $changed = DB::connection('tenant')->transaction(function () use ($aluno, $data): bool {
            $locked = Student::query()->lockForUpdate()->findOrFail($aluno);

            // Outra pessoa salvou depois que este formulário foi aberto.
            if ((string) $locked->updated_at?->format('Y-m-d H:i:s') !== $data['versao']) {
                throw ValidationException::withMessages([
                    'versao' => 'Este cadastro foi alterado por outra pessoa enquanto você editava. Abra a ficha de novo e repita a alteração.',
                ]);
            }

            $locked->fill([
                'full_name' => $data['full_name'],
                'birth_date' => $data['birth_date'] ?? null,
            ]);

            if (! $locked->isDirty()) {
                return false;
            }

            $locked->save();

            return true;
        });

        return redirect()
            ->route('school.students.show', ['municipio' => $municipality->slug, 'escola' => $school->id, 'aluno' => $aluno])
            ->with('status', $changed ? 'Dados do aluno atualizados.' : 'Nenhuma alteração foi feita.');
    }

    /** @return array{0: Municipality, 1: School} */
    private function authorizeSchool(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    /** Páginas de formulário levam ao desafio do segundo fator e voltam depois. */
    private function requireTwoFactor(Request $request): ?RedirectResponse
    {
        if (app(TwoFactorSession::class)->isVerified($request, $request->user()->fresh())) {
            return null;
        }

        return redirect()->guest(route('two-factor.challenge'));
    }

    private function activeStudentOf(School $school): Builder
    {
        return Student::query()->whereHas('enrollments', function (Builder $enrollments) use ($school): void {
            $enrollments->where('school_id', $school->id)->where('status', 'active');
        });
    }

    private function openYears()
    {
        return SchoolYear::query()->where('status', '!=', 'completed')->orderByDesc('year')->get();
    }

    /** Remove espaços sobrando; não altera maiúsculas nem acentos digitados. */
    private function normalizeName(mixed $name): mixed
    {
        return is_string($name) ? preg_replace('/\s+/u', ' ', trim($name)) : $name;
    }

    private function possibleDuplicate(string $name, ?string $birthDate): bool
    {
        $query = Student::query()->whereRaw('public.f_unaccent(lower(full_name)) = public.f_unaccent(lower(?))', [$name]);

        if ($birthDate !== null) {
            $query->whereDate('birth_date', $birthDate);
        }

        return $query->exists();
    }
}
