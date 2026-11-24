<?php

namespace App\Educacenso;

use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassTeacherAssignmentCensus;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolClassCensus;

final class Registro50
{
    /** @var array<int, string|null> */
    public array $valores = [];

    /** @var list<Pendencia> */
    public array $pendencias = [];

    /** @return array<int, string> somente áreas que o registro 20 marca como 1 */
    public static function componentesOferecidos(?SchoolClassCensus $turma, Tabelas $tabelas): array
    {
        if (! in_array($turma?->class_type, [6, 9], true) || $turma?->inep_stage_code === null || in_array($turma->inep_stage_code, [1, 2, 3], true)) {
            return [];
        }

        return array_intersect_key($tabelas->areasDoConhecimento(), $tabelas->camposDasAreas(), array_flip($turma->curricular_areas ?? []));
    }

    public static function para(School $escola, ClassTeacherAssignment $atribuicao, ?ClassTeacherAssignmentCensus $vinculo, ?SchoolClassCensus $turma, ?SchoolCensus $censo, ?int $ano = null): self
    {
        $ano ??= Tabelas::anoMaisRecente();
        $leiaute = Leiaute::do($ano);
        $tabelas = Tabelas::do($ano);
        $registro = new self;
        $v = array_fill(1, 38, null);
        $v[1] = '50';
        $v[2] = $escola->inep_code;
        $v[3] = CodigoCenso::dePessoa($atribuicao->staff_member_id);
        $v[5] = $turma?->census_code;
        $funcao = $vinculo?->class_function;
        $v[7] = $funcao === null ? null : (string) $funcao;
        $v[8] = $vinculo?->employment_status === null ? null : (string) $vinculo->employment_status;
        $componentes = $vinculo?->components ?? [];
        if (count($componentes) > 25) {
            $registro->recusa(9, 'Informe no máximo 25 componentes.');
        }
        $anterior = false;
        $vistos = [];
        $oferecidos = self::componentesOferecidos($turma, $tabelas);
        foreach ($componentes as $i => $codigo) {
            if (! is_int($i) || $i < 0 || $i > 24 || ! is_int($codigo) && ! is_string($codigo)) {
                $registro->recusa(9, 'Componentes devem formar uma lista sequencial de códigos.');

                continue;
            }
            $campo = 9 + $i;
            $v[$campo] = (string) $codigo;
            // Campos 9 a 33, regras 1 e regras de grupo 1 e 2.
            if (! in_array($funcao, [1, 5], true) || ($i > 0 && ! $anterior) || in_array($v[$campo], $vistos, true)) {
                $registro->recusa($campo, 'Componentes só para funções 1 e 5, em sequência e sem repetição.');
            }
            if (! isset($tabelas->areasDoConhecimento()[$codigo])) {
                $registro->recusa($campo, 'Código fora da tabela oficial de áreas.');
            } elseif ($turma !== null && ! isset($oferecidos[$codigo])) {
                $registro->recusa($campo, 'Componente não oferecido no registro 20 da turma.');
            }
            $anterior = true;
            $vistos[] = $v[$campo];
            if ($i !== count($vistos) - 1) {
                $registro->recusa($campo, 'Não deixe lacunas entre os códigos.');
            }
        }
        $registro->valores = $v;
        foreach ($leiaute->campos('50') as $numero => $campo) {
            foreach (ValidadorDeCampo::conferir($campo, $v[$numero]) as $problema) {
                $registro->recusa($numero, "{$campo->nome}: {$problema}.");
            }
        }
        // Campo 7, regras 2 a 6.
        $mediacao = $turma?->mediation;
        $tipo = $turma?->class_type;
        if ($turma === null || $mediacao === null || $tipo === null) {
            $registro->aviso(7, 'Complete os dados do registro 20 para conferir função e componentes.');
        }
        if ($mediacao === 3 && in_array($funcao, [1, 2, 3, 4, 7, 8], true) || $mediacao !== null && $mediacao !== 3 && in_array($funcao, [5, 6], true)) {
            $registro->recusa(7, 'Função incompatível com a mediação da turma.');
        }
        if ($tipo !== null && ($funcao === 2 && ! in_array($tipo, [6, 9], true) || $funcao === 3 && ! in_array($tipo, [4, 9], true))) {
            $registro->recusa(7, 'Função incompatível com o tipo de turma.');
        }
        if ($funcao === 9) {
            $registro->recusa(7, 'Instrutor exige itinerário técnico e profissional, fora do escopo municipal (campo 7, regra 6).');
        }
        // Campo 8, regra 1.
        $dep = $censo?->administrative_dependency;
        $funcaoComRegime = in_array($funcao, [1, 5, 6], true);
        if ($funcaoComRegime && $dep === null) {
            $registro->aviso(8, 'Confirme a dependência administrativa no registro 00.');
        }
        if ($funcaoComRegime && in_array($dep, [1, 2, 3], true) && $v[8] === null) {
            $registro->recusa(8, 'Informe a situação funcional do docente de escola pública.');
        }
        if ((! $funcaoComRegime || $dep === 4) && $v[8] !== null) {
            $registro->recusa(8, 'Situação funcional não se aplica a esta função ou dependência.');
        }
        // Campos 3 a 5 e campo 7, regras 7 e 8: dependem de outros vínculos.
        $registro->aviso(3, 'Conferir correspondência com o registro 30 e ausência de vínculo simultâneo como aluno nesta turma.');
        $registro->aviso(7, 'Conferir se a turma não tem somente intérpretes de Libras ou tutores EAD.');
        if ($funcao === 4) {
            $registro->aviso(7, 'A função 4 exige aluno ou outro profissional com surdez, deficiência auditiva ou surdocegueira na turma.');
        }
        if (in_array($censo?->operating_status, [2, 3], true)) {
            $registro->aviso(7, 'O registro 50 não é enviado para escola paralisada ou extinta (regra geral 18).');
        }

        return $registro;
    }

    public function pronto(): bool
    {
        return ! collect($this->pendencias)->contains(fn (Pendencia $p): bool => $p->recusa());
    }

    private function recusa(int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('50', $campo, $mensagem);
    }

    private function aviso(int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('50', $campo, $mensagem, Pendencia::AVISO);
    }
}
