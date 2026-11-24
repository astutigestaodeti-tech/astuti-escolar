<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentSpecialEducation;
use App\Models\Tenant\TransportRouteCensus;

/**
 * Registro 60 (vínculo do aluno com a turma) do leiaute do Educacenso.
 *
 * O campo 9 (carga horária de curso técnico ou de qualificação profissional)
 * vai nulo: a rede municipal não oferece essas etapas. Os campos 6 e 7 só
 * existem no arquivo de exportação do Inep. Cada regra cita o campo e a
 * regra do leiaute.
 */
final class Registro60
{
    /** Etapas de turma que exigem a etapa individual do aluno (campo 8). */
    private const ETAPAS_MULTI = [3, 22, 23, 56, 72, 64];

    /** @var array<int, string|null> */
    public array $valores = [];

    /** @var list<Pendencia> */
    public array $pendencias = [];

    public function __construct(
        private readonly Leiaute $leiaute,
        private readonly School $escola,
        private readonly StudentEnrollment $matricula,
        private readonly ?SchoolClassCensus $turma,
        private readonly ?StudentCensus $censo,
        private readonly ?StudentSpecialEducation $especial,
        private readonly ?TransportRouteCensus $rota,
    ) {
        $this->montar();
    }

    public static function para(School $escola, StudentEnrollment $matricula, ?SchoolClassCensus $turma, ?StudentCensus $censo, ?StudentSpecialEducation $especial, ?TransportRouteCensus $rota, ?int $ano = null): self
    {
        $ano ??= Tabelas::anoMaisRecente();

        return new self(Leiaute::do($ano), $escola, $matricula, $turma, $censo, $especial, $rota);
    }

    public function pronto(): bool
    {
        foreach ($this->pendencias as $pendencia) {
            if ($pendencia->recusa()) {
                return false;
            }
        }

        return true;
    }

    private function montar(): void
    {
        $m = $this->matricula;
        $t = $this->turma;
        $v = array_fill(1, 33, null);
        $v[1] = '60';
        $v[2] = $this->escola->inep_code;
        $v[3] = CodigoCenso::dePessoa($m->student_id);
        $v[5] = $t?->census_code;

        $etapa = $t?->inep_stage_code;
        $tipo = $t?->class_type;
        $mediacao = $t?->mediation;
        if (in_array($etapa, self::ETAPAS_MULTI, true)) {
            $v[8] = $m->inep_stage_code === null ? null : (string) $m->inep_stage_code;
        }

        // Campos 10 a 20: atividades do AEE, só em turma de AEE (tipo 5).
        if ($tipo === 5) {
            $atividades = $this->especial?->aee_activities ?? [];
            foreach (StudentSpecialEducation::AEE_ACTIVITY_FIELDS as $chave => $campo) {
                $v[$campo] = in_array($chave, $atividades, true) ? '1' : '0';
            }
        }

        $curricular = in_array($tipo, [6, 9], true);
        // Campo 21, regra 1.
        if ($curricular && $mediacao === 1 && $t?->differentiated_location === 0) {
            $v[21] = $m->other_schooling_space === null ? null : (string) $m->other_schooling_space;
        }
        // Campos 22 a 33, regra 1 do campo 22: presencial ou semipresencial, curricular e residente no Brasil.
        if ($curricular && in_array($mediacao, [1, 2], true) && $this->censo?->residence_country_code === StudentCensus::BRASIL) {
            $usa = $m->transport_status === 'active';
            $v[22] = $usa ? '1' : '0';
            if ($usa && $this->rota !== null) {
                $v[23] = $this->rota->public_power === null ? null : (string) $this->rota->public_power;
                foreach (TransportRouteCensus::VEHICLES as $chave => $dados) {
                    $v[$dados['campo']] = in_array($chave, $this->rota->vehicles ?? [], true) ? '1' : '0';
                }
            }
        }
        $this->valores = $v;

        if ($t !== null) {
            foreach ($this->leiaute->campos('60') as $numero => $campo) {
                foreach (ValidadorDeCampo::conferir($campo, $v[$numero]) as $problema) {
                    $this->recusa($numero, "{$campo->nome}: {$problema}.");
                }
            }
        }
        $this->conferirRegras($etapa, $tipo, $curricular, $mediacao);
    }

    private function conferirRegras(?int $etapa, ?int $tipo, bool $curricular, ?int $mediacao): void
    {
        $v = $this->valores;
        $m = $this->matricula;
        if ($m->school_class_id === null) {
            $this->recusa(5, 'O aluno não está em nenhuma turma: o Educacenso recusa matrícula sem turma.');

            return;
        }
        if ($this->turma === null) {
            $this->recusa(5, 'A turma do aluno ainda não tem os dados do Censo preenchidos.');

            return;
        }

        // Campo 8, regras 2 a 6.
        if (in_array($etapa, self::ETAPAS_MULTI, true)) {
            $permitidas = match ($etapa) {
                3 => [1, 2],
                22, 23 => [14, 15, 16, 17, 18, 19, 20, 21, 41],
                72 => [69, 70],
                56 => [1, 2, 14, 15, 16, 17, 18, 19, 20, 21, 41],
                64 => [39, 40],
            };
            if ($m->inep_stage_code === null) {
                $this->recusa(8, 'Turma multi: informe a etapa individual do aluno na matrícula.');
            } elseif (! in_array($m->inep_stage_code, $permitidas, true)) {
                $this->recusa(8, 'A etapa individual do aluno não pertence às etapas da turma multi.');
            }
        }

        // Campos 10 a 20, regra de grupo: pelo menos uma atividade em turma de AEE.
        if ($tipo === 5 && ! in_array('1', array_slice($v, 9, 11, true), true)) {
            $this->recusa(10, 'Aluno em turma de AEE: marque pelo menos uma atividade do AEE na educação especial.');
        }

        if ($curricular && $mediacao === 1 && $this->turma->differentiated_location === 0 && $v[21] === null) {
            $this->recusa(21, 'Informe se o aluno recebe escolarização fora da escola (dados do Censo do aluno).');
        }

        if ($v[22] === '1') {
            if ($this->rota === null) {
                $this->recusa(23, blank($m->transport_route)
                    ? 'O aluno usa transporte, mas sem rota: informe a rota para o Censo saber o veículo.'
                    : 'A rota do aluno ainda não tem poder público e veículo em "Transporte no Censo".');
            } else {
                $veiculos = array_slice($v, 23, 10, true);
                if (! in_array('1', $veiculos, true) || ! in_array('0', $veiculos, true)) {
                    $this->recusa(24, 'Campos 24 a 33: o Censo não aceita nenhum veículo nem todos marcados.');
                }
            }
        }
    }

    private function recusa(int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('60', $campo, $mensagem);
    }
}
