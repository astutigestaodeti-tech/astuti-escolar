<?php

namespace App\Educacenso;

use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassTeacherAssignmentCensus;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffCensus;
use App\Models\Tenant\StaffManagerCensus;
use App\Models\Tenant\StaffMember;
use Illuminate\Support\Collection;

/** Conferência dos servidores elegíveis, com consultas em lote. */
final class ConferenciaDeServidores
{
    public function para(Collection $escolas, ?SchoolYear $ano, ?string $dataReferencia = null): Collection
    {
        $dataReferencia ??= today()->toDateString();
        $ids = $escolas->pluck('id');
        $escolasCenso = SchoolCensus::query()->whereIn('school_id', $ids)->get()->keyBy('school_id');
        $turmas = $ano === null ? collect() : SchoolClass::query()->whereIn('school_id', $ids)
            ->where('school_year_id', $ano->id)->where('status', 'active')->get(['id', 'school_id']);
        $turmasPorId = $turmas->keyBy('id');
        $turmasCenso = SchoolClassCensus::query()->whereIn('school_class_id', $turmas->pluck('id'))->get()->keyBy('school_class_id');
        $atribuicoes = ClassTeacherAssignment::query()->whereIn('school_class_id', $turmas->pluck('id'))
            ->where('starts_on', '<=', $dataReferencia)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $dataReferencia))
            ->get()->groupBy('staff_member_id');
        $servidores = StaffMember::query()->whereIn('school_id', $ids)->where('status', 'active')
            ->where(fn ($query) => $query->whereIn('role', ['director', 'coordinator'])->orWhereIn('id', $atribuicoes->keys()))
            ->orderBy('full_name')->get();
        $pessoas = StaffCensus::query()->whereIn('staff_member_id', $servidores->pluck('id'))->get()->keyBy('staff_member_id');
        $gestores = StaffManagerCensus::query()->whereIn('staff_member_id', $servidores->pluck('id'))->get()->keyBy('staff_member_id');
        $vinculos = ClassTeacherAssignmentCensus::query()->whereIn('assignment_id', $atribuicoes->flatten(1)->pluck('id'))->get()->keyBy('assignment_id');
        $escolasPorId = $escolas->keyBy('id');
        $leiaute = Tabelas::anoMaisRecente();

        return $servidores->groupBy('school_id')->map(function (Collection $lista, string $escolaId) use ($escolasPorId, $escolasCenso, $turmasPorId, $turmasCenso, $atribuicoes, $pessoas, $gestores, $vinculos, $leiaute): array {
            $escola = $escolasPorId[$escolaId];
            $censo = $escolasCenso->get($escolaId);
            $ativa = ! in_array($censo?->operating_status, [2, 3], true);
            $linhas = [];
            foreach ($lista as $servidor) {
                $gestor = StaffManagerCensus::eligible($servidor);
                $emSala = $ativa ? $atribuicoes->get($servidor->id, collect())
                    ->filter(fn (ClassTeacherAssignment $a): bool => $turmasPorId->get($a->school_class_id)?->school_id === $escolaId) : collect();
                if (! $gestor && $emSala->isEmpty()) {
                    continue;
                }
                $r30 = Registro30Servidor::para($escola, $servidor, $pessoas->get($servidor->id), $gestor, $emSala->isNotEmpty(), $leiaute);
                $r40 = $gestor ? Registro40::para($escola, $servidor, $gestores->get($servidor->id), $censo, $leiaute) : null;
                $r50 = $emSala->map(fn (ClassTeacherAssignment $a): array => [
                    'atribuicao' => $a,
                    'registro' => Registro50::para($escola, $a, $vinculos->get($a->id), $turmasCenso->get($a->school_class_id), $censo, $leiaute),
                ])->values()->all();
                $pronto = $r30->pronto() && ($r40 === null || $r40->pronto())
                    && ! collect($r50)->contains(fn (array $item): bool => ! $item['registro']->pronto());
                $linhas[] = ['servidor' => $servidor, 'r30' => $r30, 'r40' => $r40, 'r50' => $r50, 'pronto' => $pronto];
            }

            return $linhas;
        });
    }
}
