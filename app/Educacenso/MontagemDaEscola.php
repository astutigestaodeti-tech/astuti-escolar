<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolInfrastructureCensus;
use App\Models\Tenant\SchoolYear;

/**
 * Montagem lógica de uma escola, sem arquivo, serialização ou escrita.
 */
final class MontagemDaEscola
{
    public function __construct(
        private readonly ConferenciaDeTurmas $turmas,
        private readonly ConferenciaDeAlunos $alunos,
        private readonly ConferenciaDeServidores $servidores,
        private readonly RegrasDaEscola $regras,
    ) {}

    public function para(School $escola, ?SchoolYear $ano): array
    {
        $edicao = Tabelas::anoMaisRecente();
        $censo = SchoolCensus::query()->find($escola->id);
        $infra = SchoolInfrastructureCensus::query()->where('school_id', $escola->id)->where('census_year', $edicao)->first();
        $origemEscola = ['tipo' => 'escola', 'id' => $escola->id, 'nome' => $escola->name];
        $registros = [];
        $registros[] = $this->linha('00', Registro00::para($escola, $censo, $ano, $edicao), $origemEscola);
        $ativa = ! in_array($censo?->operating_status, [2, 3], true);
        if ($ativa) {
            $registros[] = $this->linha('10', Registro10::para($escola, $infra, $censo, $edicao), [...$origemEscola, 'tipo' => 'infraestrutura']);
        }
        $rede = collect([$escola]);
        $pessoas = [];
        $vinculos = [];
        if ($ativa && $ano !== null) {
            foreach ($this->turmas->para($rede, $ano)->get($escola->id, []) as $item) {
                $turma = $item['turma'];
                $registros[] = $this->linha('20', $item['registro'], ['tipo' => 'turma', 'id' => $turma->id, 'turma' => $turma->id, 'nome' => $turma->name]);
            }
            foreach ($this->alunos->para($rede, $ano)->get($escola->id, []) as $item) {
                $m = $item['matricula'];
                $origem = ['tipo' => 'aluno', 'id' => $m->student_id, 'pessoa' => 'aluno:'.$m->student_id, 'nome' => $m->student?->full_name ?? 'Aluno'];
                $linha = $this->linha('30', $item['r30'], $origem);
                // O mesmo aluno pode ter diversos vínculos, inclusive AEE.
                // Preserva as pendências de TODOS os contextos do registro 30.
                if (isset($pessoas[$origem['pessoa']])) {
                    $pessoas[$origem['pessoa']]['pendencias'] = $this->unicas([...$pessoas[$origem['pessoa']]['pendencias'], ...$linha['pendencias']]);
                } else {
                    $pessoas[$origem['pessoa']] = $linha;
                }
                $vinculos[] = $this->linha('60', $item['r60'], [...$origem, 'turma' => $m->school_class_id ?? '', 'matricula' => $m->id]);
            }
        }
        foreach ($this->servidores->para($rede, $ano)->get($escola->id, []) as $item) {
            $s = $item['servidor'];
            $origem = ['tipo' => 'servidor', 'id' => $s->id, 'pessoa' => 'servidor:'.$s->id, 'nome' => $s->full_name];
            $pessoas[$origem['pessoa']] = $this->linha('30', $item['r30'], $origem);
            if ($item['r40'] !== null) {
                $vinculos[] = $this->linha('40', $item['r40'], $origem);
            }
            if ($ativa) {
                foreach ($item['r50'] as $vinculo) {
                    $a = $vinculo['atribuicao'];
                    $vinculos[] = $this->linha('50', $vinculo['registro'], [...$origem, 'tipo' => 'atribuicao', 'id' => $a->id, 'turma' => $a->school_class_id]);
                }
            }
        }
        $registros = [...$registros, ...array_values($pessoas), ...$vinculos];
        usort($registros, fn (array $a, array $b): int => strcmp($a['registro'], $b['registro']));
        $pendencias = [];
        foreach ($registros as $linha) {
            foreach ($linha['pendencias'] as $p) {
                $pendencias[] = ['pendencia' => $p, 'origem' => $linha['origem'], 'regra' => null];
            }
        }
        $pendencias = [...$pendencias, ...$this->regras->conferir($registros)];
        if ($ativa && ($ano === null || $ano->year !== $edicao)) {
            $pendencias[] = ['pendencia' => new Pendencia('00', null, "Cadastre o ano letivo de {$edicao} para conferir a escola."), 'origem' => $origemEscola, 'regra' => null];
        }
        $contagens = array_fill_keys(['00', '10', '20', '30', '40', '50', '60'], 0);
        foreach ($registros as $linha) {
            $contagens[$linha['registro']]++;
        }
        $recusas = count(array_filter($pendencias, fn ($item) => $item['pendencia']->recusa()));

        return ['registros' => $registros, 'pendencias' => $pendencias, 'contagens' => $contagens, 'recusas' => $recusas, 'pronta' => $recusas === 0];
    }

    private function linha(string $tipo, object $registro, array $origem): array
    {
        // Regras gerais 25-27 e 30.17.2 são conferidas sobre os vínculos reais
        // pela montagem, em vez de repetir a conferência preliminar.
        $pendencias = array_values(array_filter($registro->pendencias, fn (Pendencia $p): bool => ! in_array($p->regra, ['25', '26', '27', '30.17.2', '50.7.8'], true)));

        return ['registro' => $tipo, 'valores' => $registro->valores, 'pendencias' => $pendencias, 'origem' => $origem];
    }

    private function unicas(array $pendencias): array
    {
        $saida = [];
        foreach ($pendencias as $p) {
            $saida[json_encode([$p->registro, $p->campo, $p->gravidade, $p->mensagem])] = $p;
        }

        return array_values($saida);
    }
}
