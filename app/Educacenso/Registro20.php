<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;

/**
 * Registro 20 (turma) do leiaute de importação do Educacenso.
 *
 * Escopo da rede municipal: educação infantil, ensino fundamental (inclusive
 * multi, correção de fluxo e multietapa), EJA do ensino fundamental, AEE e
 * atividade complementar. Campos de ensino médio e educação profissional
 * (25 a 27 e 30 a 38) vão sempre nulos; as etapas que os exigiriam não são
 * oferecidas no cadastro.
 *
 * Cada regra condicional abaixo cita o campo e a regra do leiaute do Inep.
 */
final class Registro20
{
    /** Etapas oferecidas no cadastro da turma (Tabela de Etapas, agregadas 301, 302, 303 e EJA fundamental). */
    public const ETAPAS_DA_REDE = [1, 2, 3, 14, 15, 16, 17, 18, 19, 20, 21, 41, 22, 23, 56, 69, 70, 72];

    private const DIAS = ['sunday' => 7, 'monday' => 8, 'tuesday' => 9, 'wednesday' => 10, 'thursday' => 11, 'friday' => 12, 'saturday' => 13];

    /** @var array<int, string|null> */
    public array $valores = [];

    /** @var list<Pendencia> */
    public array $pendencias = [];

    public function __construct(
        private readonly Leiaute $leiaute,
        private readonly Tabelas $tabelas,
        private readonly School $escola,
        private readonly SchoolClass $turma,
        private readonly ?SchoolClassCensus $censo,
        private readonly int $alunos,
        private readonly int $professores,
    ) {
        $this->montar();
    }

    /**
     * @param  int  $alunos  matrículas ativas na turma (viram registros 60)
     * @param  int  $professores  professores com atribuição vigente (viram registros 50)
     */
    public static function para(School $escola, SchoolClass $turma, ?SchoolClassCensus $censo, int $alunos, int $professores, ?int $ano = null): self
    {
        $ano ??= Tabelas::anoMaisRecente();

        return new self(Leiaute::do($ano), Tabelas::do($ano), $escola, $turma, $censo, $alunos, $professores);
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
        $c = $this->censo;
        if ($c === null) {
            $this->pendencias[] = new Pendencia('20', null, 'Os dados da turma para o Censo ainda não foram preenchidos.');
        }

        $valores = array_fill(1, 66, null);
        $valores[1] = '20';
        $valores[2] = $this->escola->inep_code;
        $valores[3] = $c?->census_code;
        $valores[5] = Texto::normalizar($this->turma->name);
        $valores[6] = $this->codigo($c?->mediation);

        foreach (self::DIAS as $dia => $campo) {
            $valores[$campo] = $c?->{'schedule_'.$dia};
        }
        $valores[14] = $this->codigo($c?->class_type);

        $atividades = array_values(array_map('intval', $c?->complementary_activities ?? []));
        foreach (array_slice($atividades, 0, 6) as $i => $codigo) {
            $valores[15 + $i] = (string) $codigo;
        }
        $valores[21] = $this->codigo($c?->differentiated_location);

        $tipo = $c?->class_type;
        $curricular = in_array($tipo, [6, 9], true);
        $etapa = $c?->inep_stage_code;
        $dadosEtapa = $etapa === null ? null : $this->tabelas->etapa($etapa);
        if ($curricular) {
            $valores[22] = $this->bool($c?->special_class);
            $valores[23] = $dadosEtapa === null ? null : (string) $dadosEtapa['agregada'];
            $valores[24] = $this->codigo($etapa);
        }
        $semOrganizacao = $etapa === null || in_array($etapa, [1, 2, 3], true);
        $valores[28] = $semOrganizacao ? null : $this->codigo($c?->organization);
        $valores[29] = $this->bool($c?->alternance);

        $areas = array_values(array_map('intval', $c?->curricular_areas ?? []));
        if ($curricular && $etapa !== null && ! in_array($etapa, [1, 2, 3], true)) {
            foreach ($this->tabelas->camposDasAreas() as $codigo => $campo) {
                $valores[$campo] = in_array($codigo, $areas, true) ? '1' : '0';
            }
        }
        $valores[66] = $this->bool($c?->bilingual_deaf);
        $this->valores = $valores;

        if ($c !== null) {
            $this->conferirCampos();
            $this->conferirRegras($c, $tipo, $etapa, $dadosEtapa, $atividades, $areas);
        }
        if (blank($this->escola->inep_code)) {
            $this->pendencias[] = new Pendencia('20', 2, 'A escola não tem código Inep cadastrado.');
        }
        // Regras gerais 26 e 27: toda turma com pelo menos um aluno e um profissional.
        if ($this->alunos === 0) {
            $this->pendencias[] = new Pendencia('20', null, 'A turma não tem aluno matriculado: o Educacenso recusa turma sem aluno.');
        }
        if ($this->professores === 0) {
            $this->pendencias[] = new Pendencia('20', null, 'A turma não tem professor vinculado: o Educacenso recusa turma sem profissional.');
        }
    }

    private function conferirCampos(): void
    {
        foreach ($this->leiaute->campos('20') as $numero => $campo) {
            foreach (ValidadorDeCampo::conferir($campo, $this->valores[$numero]) as $problema) {
                $this->pendencias[] = new Pendencia('20', $numero, "{$campo->nome}: {$problema}.");
            }
        }
    }

    /**
     * @param  array{codigo: int, nome: string, agregada: int, nome_agregada: string}|null  $dadosEtapa
     * @param  list<int>  $atividades
     * @param  list<int>  $areas
     */
    private function conferirRegras(SchoolClassCensus $c, ?int $tipo, ?int $etapa, ?array $dadosEtapa, array $atividades, array $areas): void
    {
        $mediacao = $c->mediation;
        $comHorario = array_filter(array_keys(self::DIAS), fn (string $dia): bool => $c->{'schedule_'.$dia} !== null);

        // Campos 7 a 13, regra de grupo e regra 1 de cada dia.
        if ($mediacao === 1 && $comHorario === []) {
            $this->recusa(8, 'Informe o horário de pelo menos um dia da semana para turma presencial.');
        }
        if ($mediacao !== null && $mediacao !== 1 && $comHorario !== []) {
            $this->recusa(8, 'Turma que não é presencial não informa horário.');
        }

        // Campos 15 a 20: atividades complementares quando o tipo for 4 ou 9.
        if (in_array($tipo, [4, 9], true)) {
            if ($atividades === []) {
                $this->recusa(15, 'Informe pelo menos uma atividade complementar.');
            }
            if (count($atividades) > 6) {
                $this->recusa(15, 'São no máximo 6 atividades complementares por turma.');
            }
            if (count($atividades) !== count(array_unique($atividades))) {
                $this->recusa(15, 'Há atividade complementar repetida.');
            }
            $validas = $this->tabelas->atividadesComplementares();
            foreach ($atividades as $codigo) {
                if (! isset($validas[$codigo])) {
                    $this->recusa(15, "Atividade complementar {$codigo} não existe na tabela do Inep.");
                }
            }
        } elseif ($atividades !== []) {
            $this->recusa(15, 'Só turma de atividade complementar informa atividades complementares.');
        }

        // Campo 21, regra 5. As regras 2 a 4 dependem do registro 10 (infraestrutura da escola).
        if ($c->differentiated_location === 1 && in_array($mediacao, [2, 3], true)) {
            $this->recusa(21, 'Sala anexa só vale para turma presencial.');
        }

        if (in_array($tipo, [6, 9], true)) {
            if ($c->special_class === null) {
                $this->recusa(22, 'Informe se é classe especial.');
            }
            if ($etapa === null) {
                $this->recusa(24, 'Informe a etapa de ensino do Inep.');
            } elseif (! in_array($etapa, self::ETAPAS_DA_REDE, true) || $dadosEtapa === null) {
                $this->recusa(24, 'Etapa fora das oferecidas pela rede municipal.');
            }
            // Campo 24, regra 5.
            if ($etapa !== null && in_array($etapa, [1, 2, 3, 56], true) && in_array($c->differentiated_location, [2, 3], true)) {
                $this->recusa(24, 'Educação infantil e multietapa não funcionam em unidade socioeducativa ou prisional.');
            }
            // Campo 24, regra 6: a função de cada profissional (docente) vem no registro 50.
        } else {
            if ($etapa !== null) {
                $this->recusa(24, 'Turma de AEE ou de atividade complementar não tem etapa.');
            }
            if ($c->special_class !== null) {
                $this->recusa(22, 'Classe especial só se aplica a turma curricular.');
            }
        }

        // Campo 24, regra 4: combinação do Anexo 7.
        if ($mediacao !== null && $tipo !== null && ! $this->tabelas->combinacaoPermitida($mediacao, $tipo, in_array($tipo, [6, 9], true) ? $etapa : null)) {
            $this->recusa(24, 'A combinação de tipo de mediação, tipo de turma e etapa não é permitida pelo Inep (Anexo 7).');
        }

        // Campo 28: forma de organização (regras 1 e 2, Anexo 6).
        if ($etapa !== null && ! in_array($etapa, [1, 2, 3], true)) {
            if ($c->organization === null) {
                $this->recusa(28, 'Informe a forma de organização da turma.');
            } elseif (! in_array($c->organization, $this->tabelas->organizacoes($etapa), true)) {
                $this->recusa(28, 'Forma de organização não permitida para a etapa (Anexo 6).');
            }
        }

        // Campo 29, regra 2 (e Anexo 6). A obrigatoriedade vem da conferência do campo.
        if ($c->alternance && ($etapa === null || ! $this->tabelas->alternanciaPermitida($etapa))) {
            $this->recusa(29, 'Formação por alternância não é permitida para esta etapa.');
        }

        // Campos 39 a 65: regra de grupo e regra 2 de cada área.
        if ($etapa !== null && in_array($tipo, [6, 9], true) && ! in_array($etapa, [1, 2, 3], true)) {
            if ($areas === []) {
                $this->recusa(39, 'Marque pelo menos uma área do conhecimento ou componente curricular oferecido.');
            }
            $permitidas = $this->tabelas->areasPermitidas($etapa);
            foreach ($areas as $area) {
                if (! in_array($area, $permitidas, true)) {
                    $this->recusa($this->tabelas->camposDasAreas()[$area] ?? 39, "A área {$area} não pode ser oferecida nesta etapa (Tabela de Regras de Áreas do Conhecimento).");
                }
            }
        } elseif ($areas !== []) {
            $this->recusa(39, 'Esta turma não informa áreas do conhecimento (educação infantil, AEE ou atividade complementar).');
        }
    }

    private function recusa(int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('20', $campo, $mensagem);
    }

    private function codigo(?int $valor): ?string
    {
        return $valor === null ? null : (string) $valor;
    }

    private function bool(?bool $valor): ?string
    {
        return $valor === null ? null : ($valor ? '1' : '0');
    }
}
