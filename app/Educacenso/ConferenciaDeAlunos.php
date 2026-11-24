<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentSpecialEducation;
use App\Models\Tenant\TransportRouteCensus;
use Illuminate\Support\Collection;

/**
 * Registros 30 e 60 de cada aluno com matrícula ativa no ano, com as
 * pendências. Número fixo de consultas, qualquer que seja o tamanho da rede.
 */
final class ConferenciaDeAlunos
{
    /**
     * @param  Collection<int, School>  $escolas
     * @return Collection<string, list<array{matricula: StudentEnrollment, r30: Registro30Aluno, r60: Registro60}>> por escola
     */
    public function para(Collection $escolas, SchoolYear $ano): Collection
    {
        $matriculas = StudentEnrollment::query()->whereIn('school_id', $escolas->pluck('id'))
            ->where('school_year_id', $ano->id)->where('status', 'active')
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->with('student')
            ->get();
        $alunos = $matriculas->pluck('student_id')->unique();
        $turmas = SchoolClassCensus::query()->whereIn('school_class_id', $matriculas->pluck('school_class_id')->filter()->unique())->get()->keyBy('school_class_id');
        $censos = StudentCensus::query()->whereIn('student_id', $alunos)->get()->keyBy('student_id');
        $especiais = StudentSpecialEducation::query()->whereIn('student_id', $alunos)->get()->keyBy('student_id');
        $rotas = TransportRouteCensus::query()->whereIn('school_id', $escolas->pluck('id'))->get()
            ->keyBy(fn (TransportRouteCensus $r): string => $r->school_id.'|'.$r->route_name);

        $porEscola = $escolas->keyBy('id');
        $leiaute = Tabelas::anoMaisRecente();

        return $matriculas->groupBy('school_id')->map(function (Collection $lista, string $escolaId) use ($porEscola, $turmas, $censos, $especiais, $rotas, $leiaute): array {
            $escola = $porEscola[$escolaId];

            return $lista->sortBy(fn (StudentEnrollment $m): string => mb_strtolower((string) $m->student?->full_name))
                ->map(function (StudentEnrollment $m) use ($escola, $turmas, $censos, $especiais, $rotas, $leiaute): array {
                    $turma = $m->school_class_id ? $turmas->get($m->school_class_id) : null;
                    $censo = $censos->get($m->student_id);
                    $especial = $especiais->get($m->student_id);
                    $rota = $rotas->get($escola->id.'|'.trim((string) $m->transport_route));

                    return [
                        'matricula' => $m,
                        'r30' => Registro30Aluno::para($escola, $m->student, $censo, $especial, $turma, $leiaute),
                        'r60' => Registro60::para($escola, $m, $turma, $censo, $especial, $rota, $leiaute),
                    ];
                })->values()->all();
        });
    }
}
