<?php

namespace App\Http\Controllers;

use App\Attendance\SchoolAbsenceAlerts;
use App\Attendance\SistemaPresenca;
use App\Audit\AuditTrail;
use App\Models\Tenant\PresenceRegistration;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Frequência para o Sistema Presença (Bolsa Família). Lista de todos os
 * alunos de 4 a 17 anos com a frequência de cada mês do período; o operador
 * cruza com a lista de beneficiários do próprio Presença, registra lá o
 * motivo e marca aqui o que já lançou.
 */
class SistemaPresencaController extends Controller
{
    public function __construct(private readonly SistemaPresenca $presenca, private readonly SchoolAbsenceAlerts $anos) {}

    public function network(Request $request, string $municipio): Response
    {
        $municipality = $request->attributes->get('astuti.municipality');
        Gate::authorize('viewNetworkPanel', [School::class, $municipality]);
        $ano = $this->anos->currentYear();
        $periodo = $this->periodo($request, $ano);
        $escolas = School::query()->where('status', 'active')->orderBy('name')->get();
        $linhas = $ano === null ? collect() : $this->presenca->para($escolas->pluck('id')->all(), $ano, $periodo);

        $resumo = $escolas->map(function (School $escola) use ($linhas): array {
            $lista = collect($linhas->get($escola->id, []));

            return [
                'escola' => $escola,
                'total' => $lista->count(),
                'abaixo' => $lista->where('situacao', SistemaPresenca::ABAIXO)->count(),
                'sem' => $lista->where('situacao', SistemaPresenca::SEM_CHAMADA)->count(),
                'lancados' => $lista->whereNotNull('lancado')->count(),
            ];
        });

        return response()->view('presence.network', compact('municipality', 'ano', 'periodo', 'resumo'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function school(Request $request, string $municipio, string $escola): Response
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $ano = $this->anos->currentYear();
        $periodo = $this->periodo($request, $ano);
        $filtro = $request->validate(['situacao' => ['nullable', Rule::in(array_keys(SistemaPresenca::SITUACOES))]])['situacao'] ?? null;
        $busca = mb_substr(trim((string) $request->query('busca', '')), 0, 120);
        $todas = $ano === null ? [] : ($this->presenca->para([$school->id], $ano, $periodo)->get($school->id) ?? []);
        $linhas = collect($todas)
            ->when($filtro, fn ($c) => $c->where('situacao', $filtro))
            ->when($busca !== '', fn ($c) => $c->filter(fn (array $l): bool => Str::contains(Str::lower(Str::ascii($l['nome'])), Str::lower(Str::ascii($busca)))))
            ->values();

        return response()->view('presence.school', [
            'municipality' => $municipality, 'school' => $school, 'ano' => $ano, 'periodo' => $periodo,
            'linhas' => $linhas, 'filtro' => $filtro, 'busca' => $busca,
            'contagem' => collect($todas)->countBy('situacao'), 'total' => count($todas),
            'lancados' => collect($todas)->whereNotNull('lancado')->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function mark(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $ano = $this->anos->currentYear();
        if ($ano === null) {
            return redirect()->route('school.presence.index', [
                'municipio' => $municipality->slug, 'escola' => $school->id,
            ])->withErrors(['ano_letivo' => 'Não há ano letivo disponível para registrar o lançamento.']);
        }

        $avisoAno = 'A página está desatualizada. Confira o ano letivo exibido e tente novamente.';
        $data = $request->validate([
            'aluno' => ['required', 'uuid'],
            'ano_letivo' => ['bail', 'required', 'uuid', Rule::in([$ano->id])],
            'periodo' => ['required', 'integer', Rule::in(array_keys(SistemaPresenca::PERIODOS))],
            'lancado' => ['required', Rule::in(['0', '1'])],
        ], [
            'ano_letivo.required' => $avisoAno,
            'ano_letivo.uuid' => $avisoAno,
            'ano_letivo.in' => $avisoAno,
        ]);
        $existe = StudentEnrollment::query()->where('school_id', $school->id)->where('school_year_id', $ano->id)
            ->where('student_id', $data['aluno'])->exists();
        abort_unless($existe, 404);

        DB::connection('tenant')->transaction(function () use ($school, $ano, $data, $request): void {
            $registro = PresenceRegistration::query()->where('school_id', $school->id)->where('school_year_id', $ano->id)
                ->where('student_id', $data['aluno'])->where('period', (int) $data['periodo'])->lockForUpdate()->first();
            $auditoria = app(AuditTrail::class);
            if ($data['lancado'] === '1' && $registro === null) {
                $registro = PresenceRegistration::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => $school->id, 'school_year_id' => $ano->id,
                    'student_id' => $data['aluno'], 'period' => (int) $data['periodo'],
                    'registered_by' => (string) $request->user()->getAuthIdentifier(), 'registered_at' => now(),
                ]);
                $auditoria->record('tenant', 'presence_registration.marked', PresenceRegistration::class, $registro->id, null, $registro->only(['id', 'school_id', 'school_year_id', 'student_id', 'period']));
            } elseif ($data['lancado'] === '0' && $registro !== null) {
                $antes = $registro->only(['id', 'school_id', 'school_year_id', 'student_id', 'period']);
                $registro->delete();
                $auditoria->record('tenant', 'presence_registration.unmarked', PresenceRegistration::class, $antes['id'], $antes, null);
            }
        });

        return redirect()->to(route('school.presence.index', [
            'municipio' => $municipality->slug, 'escola' => $school->id, 'periodo' => $data['periodo'],
            'situacao' => $request->input('situacao') ?: null,
        ]).'#aluno-'.$data['aluno']);
    }

    private function periodo(Request $request, ?SchoolYear $ano): int
    {
        $periodo = (int) $request->query('periodo', 0);
        if (isset(SistemaPresenca::PERIODOS[$periodo])) {
            return $periodo;
        }
        $hoje = CarbonImmutable::today();

        return $ano !== null && $hoje->year > $ano->year ? 5 : SistemaPresenca::periodoDe($hoje);
    }

    /** @return array{0: mixed, 1: School} */
    private function authorizedSchool(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }
}
