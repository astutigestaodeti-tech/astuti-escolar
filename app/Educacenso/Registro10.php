<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolCensus;
use App\Models\Tenant\SchoolInfrastructureCensus;

/** Caracterização e infraestrutura da escola (registro 10, leiaute 2026). */
final class Registro10
{
    /** @var array<int, string|null> */
    public array $valores = [];

    /** @var list<Pendencia> */
    public array $pendencias = [];

    /** Regra geral 18: escolas paralisadas ou extintas não enviam registro 10. */
    public readonly bool $aplicavel;

    /** Pais dos campos binários condicionais, também usados no formulário. */
    public const CONDICIONAIS_BINARIOS = [
        10 => [3, '1'],
        117 => [115, '0'],
        165 => [164, '1'], 166 => [164, '1'], 167 => [164, '1'],
        168 => [164, '1'], 169 => [164, '1'], 170 => [164, '1'],
        182 => [181, '1'], 183 => [181, '1'], 184 => [181, '1'],
        185 => [181, '1'], 186 => [181, '1'], 187 => [181, '1'],
    ];

    private function __construct(
        private readonly Leiaute $leiaute,
        private readonly Tabelas $tabelas,
        private readonly School $escola,
        private readonly ?SchoolInfrastructureCensus $questionario,
        private readonly ?SchoolCensus $censo,
    ) {
        $this->aplicavel = ! in_array($censo?->operating_status, [2, 3], true);
        $this->montar();
    }

    public static function para(School $escola, ?SchoolInfrastructureCensus $questionario, ?SchoolCensus $censo, int $ano): self
    {
        return new self(Leiaute::do($ano), Tabelas::do($ano), $escola, $questionario, $censo);
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
        $this->valores = array_fill(1, 187, null);
        $this->valores[1] = '10';
        $this->valores[2] = $this->escola->inep_code;
        if (! $this->aplicavel) {
            $this->aviso(null, 'Escola paralisada ou extinta: o registro 10 não deve ser enviado (regra geral 18).');

            return;
        }
        if ($this->censo?->operating_status !== 1) {
            $this->recusa(null, 'Confirme a situação de funcionamento da escola no registro 00.');
        }
        $q = $this->questionario;
        if ($q === null || $q->census_year !== $this->leiaute->ano) {
            $this->recusa(null, 'O questionário de infraestrutura deste ano ainda não foi preenchido.');

            return;
        }

        foreach ((array) $q->answers as $numero => $valor) {
            if (! is_int($numero) || $numero < 3 || $numero > 187 || ! is_string($valor)) {
                $this->recusa(null, 'O questionário contém campo ou tipo de resposta fora do leiaute.');

                continue;
            }
            $this->valores[$numero] = $valor === '' ? null : $valor;
        }
        foreach ($this->leiaute->campos('10') as $numero => $campo) {
            foreach (ValidadorDeCampo::conferir($campo, $this->valores[$numero]) as $problema) {
                $this->recusa($numero, "{$campo->nome}: {$problema}.");
            }
        }
        $this->conferirGrupos();
        $this->conferirRegras();
        $this->aviso(null, 'A montagem da escola confere os demais registros obrigatórios (regra geral 19).', '19');
        $this->aviso(null, 'A conferência integral ainda deve verificar a compatibilidade dos locais de funcionamento das turmas (registro 20, campo 21, regras 2 a 4).');
    }

    private function conferirGrupos(): void
    {
        foreach ($this->leiaute->grupos('10') as $grupo) {
            $de = $grupo['de'];
            $ate = $grupo['ate'];
            if ($de === 11) {
                // Grupo 11 a 16, regras 1 e 2: códigos distintos e sequenciais.
                $vistos = [];
                $lacuna = false;
                foreach (range($de, $ate) as $numero) {
                    $valor = $this->valores[$numero];
                    if ($valor === null) {
                        $lacuna = true;

                        continue;
                    }
                    if ($lacuna || in_array($valor, $vistos, true)) {
                        $this->recusa($numero, 'Preencha os códigos de escolas compartilhadas em sequência e sem repetir.');
                    }
                    $vistos[] = $valor;
                }

                continue;
            }
            if (($de === 165 && $this->valores[164] !== '1') || ($de === 182 && $this->valores[181] !== '1')) {
                continue;
            }
            // Os grupos 119 a 138 e 140 a 159 exigem preenchimento, não
            // necessariamente uma resposta 1. Os demais exigem ao menos um 1.
            $respostas = array_slice($this->valores, $de - 1, $ate - $de + 1);
            $atendido = in_array($de, [119, 140], true)
                ? count(array_filter($respostas, fn ($v) => $v !== null)) > 0
                : in_array('1', $respostas, true);
            if (! $atendido) {
                $this->recusa($de, $grupo['nome'].': '.(in_array($de, [119, 140], true) ? 'preencha pelo menos um campo.' : 'marque pelo menos uma opção.'));
            }
        }

        // Regra 2 dos campos de opção exclusiva, conforme a V5.
        foreach ([23 => 18, 27 => 24, 31 => 28, 80 => 41, 90 => 81, 102 => 96, 115 => 111, 159 => 140, 170 => 165, 179 => 174, 187 => 182] as $nenhum => $inicio) {
            if ($this->valores[$nenhum] === '1' && $this->algumSim($inicio, $nenhum - 1)) {
                $this->recusa($nenhum, $this->leiaute->campo('10', $nenhum)->nome.': não pode ser marcado junto com outra opção do grupo.');
            }
        }
    }

    private function conferirRegras(): void
    {
        $v = $this->valores;

        // Campos 9 e 10, regra 1; campo 11, regra 1.
        $this->condicional(9, $v[3] === '1');
        $this->condicional(11, $v[10] === '1');
        foreach (range(11, 16) as $numero) {
            if ($v[$numero] === null) {
                continue;
            }
            if ($v[10] !== '1' && $numero !== 11) {
                $this->recusa($numero, 'Só informe códigos de escolas quando houver compartilhamento do prédio.');
            }
            // Regra 2: o Inep verifica situação da escola; identidade e UF
            // são conhecidas pelo código local, mas a regra é de processamento.
            $this->aviso($numero, 'O Inep deve confirmar que a escola compartilhada está em atividade, na mesma UF e é diferente da escola atual.');
            if ($v[$numero] === $v[2]) {
                $this->recusa($numero, 'O código da escola compartilhada deve ser diferente do código desta escola.');
            }
            if ($v[2] !== null && substr($v[$numero], 0, 2) !== substr($v[2], 0, 2)) {
                $this->recusa($numero, 'O código da escola compartilhada deve ser da mesma UF.');
            }
        }

        // Campo 30, regra 2; campo 44, regra 2.
        if ($v[29] === '1' && $v[30] === '1') {
            $this->recusa(30, 'Fossa rudimentar não pode ser marcada junto com fossa séptica.');
        }
        if ($this->algumSim(45, 48) && $v[44] !== '1') {
            $this->recusa(44, 'Marque banheiro quando informar algum tipo de banheiro ou vestiário.');
        }

        // Campos 91 a 95, regras 1 e 2; campos 103 a 110, regra 1.
        $this->condicional(91, $v[3] === '1');
        if ($v[3] === '0' && $v[92] === null) {
            $this->recusa(92, 'Informe a quantidade de salas fora do prédio escolar.');
        }
        $salas = (int) $v[91] + (int) $v[92];
        foreach (range(93, 95) as $numero) {
            $this->condicional($numero, $v[91] !== null || $v[92] !== null);
            if ($v[$numero] !== null && (int) $v[$numero] > $salas) {
                $this->recusa($numero, 'Não pode superar a soma das salas dentro e fora do prédio escolar.');
            }
        }
        foreach ([...range(91, 95), ...range(103, 110), ...range(119, 137)] as $numero) {
            if ($v[$numero] !== null && ((int) $v[$numero] < 1 || (int) $v[$numero] > 9999)) {
                $this->recusa($numero, 'Informe uma quantidade de 1 a 9999 ou deixe vazio se não se aplicar.');
            }
        }

        // Campos 116 a 118, regras 1 a 3.
        $this->condicional(116, $v[113] === '1');
        if (in_array($v[116], ['1', '3'], true) && $v[108] === null && $v[109] === null && $v[110] === null) {
            $this->recusa(116, 'Informe ao menos uma quantidade de computadores ou tablets em uso pelos alunos.');
        }
        if (in_array($v[118], ['1', '3'], true) && $v[97] === '0' && $v[116] === null) {
            $this->recusa(118, 'Rede a cabo exige computadores ou equipamentos informados para uso dos alunos.');
        }
        if (in_array($v[116], ['2', '3'], true) && ! in_array($v[118], ['2', '3'], true)) {
            $this->recusa(118, 'O uso de dispositivos pessoais exige rede local Wireless.');
        }
        foreach (self::CONDICIONAIS_BINARIOS as $numero => [$pai, $esperado]) {
            $this->condicional($numero, $v[$pai] === $esperado);
        }

        // Campo 138, regra 1: somente 1 quando todos os totais são nulos.
        $temProfissionais = count(array_filter(array_slice($v, 118, 19), fn ($valor) => $valor !== null)) > 0;
        if ($temProfissionais ? $v[138] !== null : $v[138] !== '1') {
            $this->recusa(138, 'Marque que não há funcionários somente se todos os totais das funções listadas estiverem vazios.');
        }
        // Campo 139, regra 1 (o campo tem tipo num, sem opções no JSON).
        if (! in_array($v[139], ['0', '1'], true)) {
            $this->recusa(139, 'Informe se a escola oferece alimentação escolar (0 ou 1).');
        }
        // Campo 139, regra 2: depende do registro 20.
        if ($v[139] === '1') {
            $this->aviso(139, 'A alimentação escolar exige pelo menos uma turma do registro 20 com mediação 1 ou 2. A conferência entre registros fica para a montagem da escola completa.');
        }

        // Campos 161 a 163, regra 1: códigos oficiais e sem repetição.
        $usaLingua = in_array($v[160], ['1', '3'], true);
        $linguas = $usaLingua ? $this->tabelas->linguasIndigenas() : [];
        $vistos = [];
        foreach (range(161, 163) as $numero) {
            $this->condicional($numero, $usaLingua);
            if ($v[$numero] !== null) {
                if (! array_key_exists($v[$numero], $linguas) || in_array($v[$numero], $vistos, true)) {
                    $this->recusa($numero, 'Escolha um código de língua indígena válido e não repetido.');
                }
                $vistos[] = $v[$numero];
            }
        }
    }

    private function condicional(int $numero, bool $aplica): void
    {
        if ($aplica && $this->valores[$numero] === null) {
            $this->recusa($numero, $this->leiaute->campo('10', $numero)->nome.': informe quando se aplicar.');
        } elseif (! $aplica && $this->valores[$numero] !== null) {
            $this->recusa($numero, $this->leiaute->campo('10', $numero)->nome.': deixe vazio quando não se aplicar.');
        }
    }

    private function algumSim(int $de, int $ate): bool
    {
        return in_array('1', array_slice($this->valores, $de - 1, $ate - $de + 1), true);
    }

    private function recusa(?int $campo, string $mensagem): void
    {
        $this->pendencias[] = new Pendencia('10', $campo, $mensagem);
    }

    private function aviso(?int $campo, string $mensagem, ?string $regra = null): void
    {
        $this->pendencias[] = new Pendencia('10', $campo, $mensagem, Pendencia::AVISO, $regra);
    }
}
