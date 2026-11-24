<?php

namespace App\Educacenso;

use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Collection;

/**
 * Registro 20 de todas as turmas ativas de um ano, com as pendências de cada
 * uma. Número fixo de consultas, qualquer que seja o tamanho da rede.
 */
final class ConferenciaDeTurmas
{
    /**
     * @param  Collection<int, School>  $escolas
     * @return Collection<string, list<array{turma: SchoolClass, censo: ?SchoolClassCensus, registro: Registro20}>> por escola
     */
    public function para(Collection $escolas, SchoolYear $ano): Collection
    {
        $turmas = SchoolClass::query()->whereIn('school_id', $escolas->pluck('id'))
            ->where('school_year_id', $ano->id)->where('status', 'active')
            ->orderBy('name')->orderBy('id')->get(['id', 'school_id', 'school_year_id', 'name', 'stage', 'shift', 'status']);
        $ids = $turmas->pluck('id');
        $censos = SchoolClassCensus::query()->whereIn('school_class_id', $ids)->get()->keyBy('school_class_id');
        $alunos = StudentEnrollment::query()->whereIn('school_class_id', $ids)->where('status', 'active')
            ->whereHas('student', fn ($query) => $query->whereNull('deleted_at'))
            ->selectRaw('school_class_id, count(distinct student_id) as total')->groupBy('school_class_id')->pluck('total', 'school_class_id');
        $professores = ClassTeacherAssignment::query()->whereIn('school_class_id', $ids)
            ->where('starts_on', '<=', today()->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', today()->toDateString()))
            ->selectRaw('school_class_id, count(*) as total')->groupBy('school_class_id')->pluck('total', 'school_class_id');

        $porEscola = $escolas->keyBy('id');
        $leiaute = Tabelas::anoMaisRecente();

        return $turmas->groupBy('school_id')->map(function (Collection $lista, string $escolaId) use ($porEscola, $censos, $alunos, $professores, $leiaute): array {
            $linhas = $lista->map(fn (SchoolClass $turma): array => [
                'turma' => $turma,
                'censo' => $censos->get($turma->id),
                'registro' => Registro20::para($porEscola[$escolaId], $turma, $censos->get($turma->id),
                    (int) ($alunos[$turma->id] ?? 0), (int) ($professores[$turma->id] ?? 0), $leiaute),
            ])->values()->all();

            return self::repetidas($linhas);
        });
    }

    /**
     * Regra geral 25: na mesma escola, duas turmas não podem ter iguais nome,
     * mediação, horários, tipo, local diferenciado, classe especial e etapa.
     *
     * @param  list<array{turma: SchoolClass, censo: ?SchoolClassCensus, registro: Registro20}>  $linhas
     * @return list<array{turma: SchoolClass, censo: ?SchoolClassCensus, registro: Registro20}>
     */
    private static function repetidas(array $linhas): array
    {
        $chaves = [];
        foreach ($linhas as $i => $linha) {
            $v = $linha['registro']->valores;
            if ($linha['censo'] === null) {
                continue;
            }
            $chave = implode('|', [$v[5], $v[6], $v[7], $v[8], $v[9], $v[10], $v[11], $v[12], $v[13], $v[14], $v[21], $v[22], $v[24]]);
            $chaves[$chave][] = $i;
        }
        foreach ($chaves as $indices) {
            if (count($indices) > 1) {
                foreach ($indices as $i) {
                    $linhas[$i]['registro']->pendencias[] = new Pendencia('20', null,
                        'Há outra turma nesta escola com o mesmo nome, horários, tipo e etapa: o Educacenso recusa turmas iguais.', regra: '25');
                }
            }
        }

        return $linhas;
    }
}
