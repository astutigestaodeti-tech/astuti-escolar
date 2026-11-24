<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\StaffCensus;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\StudentSpecialEducation;
use App\Validation\Cpf;

/**
 * Registro 30 (pessoa física) de um servidor com vínculo de gestor
 * (registro 40) ou de profissional em sala (registro 50).
 *
 * Pelo Anexo 2, transtornos, recursos de prova, certidão (29 a 50) são só
 * de aluno e vão nulos. A residência (51 a 55) é opcional para quem não é
 * aluno e não é coletada. Cada regra condicional cita o campo do leiaute.
 */
final class Registro30Servidor
{
    private const SEXO = ['male' => '1', 'female' => '2'];

    private const COR = ['nao_declarada' => '0', 'branca' => '1', 'preta' => '2', 'parda' => '3', 'amarela' => '4', 'indigena' => '5'];

    private const NACIONALIDADE = ['brasileira' => '1', 'naturalizada' => '2', 'estrangeira' => '3'];

    /** Anexo 3: idades permitidas por função. */
    private const IDADE_GESTOR = [18, 95];

    private const IDADE_EM_SALA = [14, 95];

    /** Campos dos cursos superiores: [código, ano, IES]. */
    private const CAMPOS_CURSOS = [[58, 59, 60], [61, 62, 63], [64, 65, 66]];

    /** Campos das pós-graduações: [tipo, área, ano]. */
    private const CAMPOS_POS = [[70, 71, 72], [73, 74, 75], [76, 77, 78], [79, 80, 81], [82, 83, 84], [85, 86, 87]];

    /** @var array<int, string|null> */
    public array $valores = [];

    /** @var list<Pendencia> */
    public array $pendencias = [];

    public function __construct(
        private readonly Leiaute $leiaute,
        private readonly Tabelas $tabelas,
        private readonly School $escola,
        private readonly StaffMember $servidor,
        private readonly ?StaffCensus $censo,
        private readonly bool $gestor,
        private readonly bool $emSala,
    ) {
        $this->montar();
    }

    public static function para(School $escola, StaffMember $servidor, ?StaffCensus $censo, bool $gestor, bool $emSala, ?int $ano = null): self
    {
        $ano ??= Tabelas::anoMaisRecente();

        return new self(Leiaute::do($ano), Tabelas::do($ano), $escola, $servidor, $censo, $gestor, $emSala);
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
        $v = array_fill(1, 110, null);
        $v[1] = '30';
        $v[2] = $this->escola->inep_code;
        $v[3] = CodigoCenso::dePessoa($this->servidor->id);
        $v[5] = $c?->cpf;
        $v[6] = Texto::normalizar($this->servidor->full_name);
        $v[7] = $c?->birth_date?->format('d/m/Y');
        $v[8] = $c?->filiation === null ? null : (string) $c->filiation;
        $v[9] = $c?->filiation === 1 ? Texto::normalizar($c->parent1_name) : null;
        $v[10] = $c?->filiation === 1 ? Texto::normalizar($c->parent2_name) : null;
        $v[11] = self::SEXO[$c?->sex ?? ''] ?? null;
        $v[12] = self::COR[$c?->race_color ?? ''] ?? null;
        $v[13] = $v[12] === '5' && $c?->indigenous_people_code ? (string) $c->indigenous_people_code : null;
        $v[14] = self::NACIONALIDADE[$c?->nationality ?? ''] ?? null;
        $v[15] = $c?->nationality_country_code ? (string) $c->nationality_country_code : null;
        $v[16] = $v[14] === '1' && $c?->birth_municipality_code ? (string) $c->birth_municipality_code : null;

        // Campos 17 a 28: deficiência, TEA e altas habilidades.
        if ($c !== null) {
            $condicoes = $c->conditions ?? [];
            $v[17] = $condicoes === [] ? '0' : '1';
            if ($v[17] === '1') {
                foreach (StudentSpecialEducation::CENSUS_FIELDS['conditions'] as $chave => $campo) {
                    $v[$campo] = in_array($chave, $condicoes, true) ? '1' : '0';
                }
                $deficiencias = array_filter(range(18, 25), fn (int $campo): bool => $v[$campo] === '1');
                $v[26] = count($deficiencias) > 1 ? '1' : '0';
            }
        }

        // Campos 56 a 69: escolaridade, cursos superiores e complementação pedagógica.
        $v[56] = $c?->education_level === null ? null : (string) $c->education_level;
        if (in_array($c?->education_level, [StaffCensus::HIGH_SCHOOL, StaffCensus::HIGHER_EDUCATION], true)) {
            $v[57] = $c->high_school_type === null ? null : (string) $c->high_school_type;
        }
        if ($c?->education_level === StaffCensus::HIGHER_EDUCATION) {
            foreach (array_values($c->higher_courses ?? []) as $i => $curso) {
                if ($i >= StaffCensus::MAX_COURSES) {
                    break;
                }
                [$cCodigo, $cAno, $cIes] = self::CAMPOS_CURSOS[$i];
                $v[$cCodigo] = $curso['curso'] ?? null;
                $v[$cAno] = isset($curso['ano']) ? (string) $curso['ano'] : null;
                $v[$cIes] = isset($curso['ies']) ? (string) $curso['ies'] : null;
            }
            foreach (array_values($c->pedagogical_areas ?? []) as $i => $area) {
                if ($i < 3) {
                    $v[67 + $i] = (string) $area;
                }
            }
        }

        // Campos 70 a 88: pós-graduações concluídas.
        $pos = array_values($c?->postgraduates ?? []);
        foreach ($pos as $i => $item) {
            if ($i >= StaffCensus::MAX_POSTGRADUATES) {
                break;
            }
            [$pTipo, $pArea, $pAno] = self::CAMPOS_POS[$i];
            $v[$pTipo] = isset($item['tipo']) ? (string) $item['tipo'] : null;
            $v[$pArea] = isset($item['area']) ? (string) $item['area'] : null;
            $v[$pAno] = isset($item['ano']) ? (string) $item['ano'] : null;
        }
        $v[88] = $c?->no_postgraduate === true && $pos === [] ? '1' : null;

        // Campos 89 a 109: formação continuada; nula quando não informada.
        if ($c !== null && $c->continuing_education !== null) {
            foreach (StaffCensus::CONTINUING_EDUCATION as $chave => $item) {
                $v[$item['campo']] = in_array($chave, $c->continuing_education, true) ? '1' : '0';
            }
            $v[109] = $c->continuing_education === [] ? '1' : '0';
        }

        // Campo 110: e-mail só para quem tem vínculo de gestor (registro 40).
        $v[110] = $this->gestor ? Texto::normalizar($this->servidor->institutional_email) : null;
        $this->valores = $v;

        $this->conferirRegras();
        $this->conferirCampos();
    }

    private function conferirCampos(): void
    {
        foreach ($this->leiaute->campos('30') as $numero => $campo) {
            if ($this->censo === null && ! in_array($numero, [1, 2, 3, 6], true)) {
                continue;
            }
            foreach (ValidadorDeCampo::conferir($campo, $this->valores[$numero]) as $problema) {
                $this->recusa($numero, "{$campo->nome}: {$problema}.");
            }
        }
    }

    private function conferirRegras(): void
    {
        $v = $this->valores;
        $c = $this->censo;
        if (blank($this->escola->inep_code)) {
            $this->recusa(2, 'A escola não tem código Inep cadastrado.');
        }
        if (! $this->gestor && ! $this->emSala) {
            $this->pendencias[] = new Pendencia('30', 3, 'O servidor não tem vínculo de gestor nem de turma: não vai para o Censo.', Pendencia::AVISO);
        }
        if ($c === null) {
            $this->recusa(null, 'Os dados do Censo do servidor ainda não foram preenchidos (CPF, nascimento, filiação e formação).');

            return;
        }

        // Campo 5, regras 1 e 2: CPF obrigatório para gestor e para profissional brasileiro em sala; válido.
        if ($v[5] === null && ($this->gestor || ($this->emSala && in_array($v[14], ['1', '2'], true)))) {
            $this->recusa(5, 'Informe o CPF: é obrigatório para gestor e para profissional brasileiro em sala.');
        } elseif ($v[5] !== null && ! Cpf::isValid($v[5])) {
            $this->recusa(5, 'CPF inválido: os dígitos verificadores não conferem.');
        }
        // Campos 6, 9 e 10, regra 1: nome de pessoa sem CPF.
        if ($v[5] === null) {
            foreach ([6 => $v[6], 9 => $v[10] === null ? $v[9] : null, 10 => $v[9] === null ? $v[10] : null] as $campo => $nome) {
                if ($nome !== null && ($problemas = NomeDePessoa::problemas($nome)) !== []) {
                    $this->recusa($campo, $this->leiaute->campo('30', $campo)->nome.': '.implode('; ', $problemas).'.');
                }
            }
        }
        // Campo 7, regras 1 e 2: data não futura e idade do Anexo 3.
        if ($c->birth_date !== null) {
            $idade = $this->leiaute->ano - (int) $c->birth_date->format('Y');
            [$minima, $maxima] = $this->gestor ? self::IDADE_GESTOR : self::IDADE_EM_SALA;
            if ($c->birth_date->isFuture()) {
                $this->recusa(7, 'A data de nascimento está no futuro.');
            } elseif ($idade < $minima || $idade > $maxima) {
                $this->recusa(7, "Pelo Anexo 3, a idade de quem tem esse vínculo deve ficar entre {$minima} e {$maxima} anos.");
            }
        }
        // Campo 8, regra 2.
        if ($v[8] === '1' && $v[9] === null && $v[10] === null) {
            $this->recusa(9, 'Filiação marcada como informada, mas sem nome.');
        }
        // Campo 13: povo indígena quando a cor/raça for indígena.
        if ($v[12] === '5' && $v[13] === null) {
            $this->recusa(13, 'Informe o povo indígena.');
        }
        // Campo 15, regra 2, e campo 16.
        if ($v[14] !== null && $v[15] === null) {
            $this->recusa(15, 'Informe o país de nacionalidade.');
        } elseif ($v[14] === '3' && $v[15] === '76') {
            $this->recusa(15, 'Para estrangeiro, o país de nacionalidade não pode ser o Brasil.');
        }
        if ($v[14] === '1' && $v[16] === null) {
            $this->recusa(16, 'Informe o município de nascimento.');
        }

        // Campo 56, regra 1: escolaridade de quem tem vínculo 40 ou 50.
        if (($this->gestor || $this->emSala) && $v[56] === null) {
            $this->recusa(56, 'Informe o maior nível de escolaridade concluído.');
        }
        // Campo 57: ensino médio cursado para quem concluiu o médio ou o superior.
        if (in_array($v[56], ['6', '7'], true) && $v[57] === null) {
            $this->recusa(57, 'Informe o tipo de ensino médio cursado.');
        }
        $this->conferirCursos($v);
        $this->conferirPos($v);

        // Campo 110, regra 1: e-mail do gestor.
        if ($this->gestor && $v[110] === null) {
            $this->recusa(110, 'Informe o e-mail institucional no cadastro do servidor: o Censo exige e-mail do gestor.');
        }
    }

    /** @param  array<int, string|null>  $v */
    private function conferirCursos(array $v): void
    {
        if ($v[56] !== '6') {
            return;
        }
        // Campo 58, regra 1: com educação superior, pelo menos o curso 1.
        if ($v[58] === null) {
            $this->recusa(58, 'Com educação superior, informe pelo menos um curso superior concluído.');
        }
        $nascimento = (int) ($this->censo?->birth_date?->format('Y') ?? 0);
        $vistos = [];
        foreach (self::CAMPOS_CURSOS as [$cCodigo, $cAno, $cIes]) {
            if ($v[$cCodigo] === null) {
                continue;
            }
            if (! isset($this->tabelas->cursosSuperiores()[$v[$cCodigo]])) {
                $this->recusa($cCodigo, 'Curso fora da Tabela de Cursos de Formação Superior.');
            }
            // Campos 61 e 64: cursos diferentes entre si.
            if (in_array($v[$cCodigo], $vistos, true)) {
                $this->recusa($cCodigo, 'O mesmo curso foi informado duas vezes.');
            }
            $vistos[] = $v[$cCodigo];
            // Campos 59, 62 e 65: ano entre nascimento + 1 e o ano do Censo, não antes de 1940.
            $ano = (int) $v[$cAno];
            if ($v[$cAno] === null || $ano < max(1940, $nascimento + 1) || $ano > $this->leiaute->ano) {
                $this->recusa($cAno, "Ano de conclusão do curso deve ficar entre o ano seguinte ao nascimento e {$this->leiaute->ano}.");
            }
            // Campos 60, 63 e 66: IES da tabela.
            if ($v[$cIes] === null || $this->tabelas->ies((int) $v[$cIes]) === null) {
                $this->recusa($cIes, 'Informe a instituição de educação superior pelo código da Tabela de IES.');
            }
        }
        // Campos 67 a 69: áreas da tabela, diferentes entre si, nunca 32 ou 99.
        $areas = array_filter([$v[67], $v[68], $v[69]], fn (?string $a): bool => $a !== null);
        $tabela = $this->tabelas->areasDoConhecimento();
        foreach ($areas as $campo => $area) {
            if (! isset($tabela[(int) $area]) || in_array((int) $area, StaffCensus::AREAS_NOT_FOR_COMPLEMENT, true)) {
                $this->recusa(67 + $campo, 'Área da complementação pedagógica fora da tabela ou não aceita (32 e 99).');
            }
        }
        if (count($areas) !== count(array_unique($areas))) {
            $this->recusa(68, 'A mesma área da complementação pedagógica foi informada duas vezes.');
        }
    }

    /** @param  array<int, string|null>  $v */
    private function conferirPos(array $v): void
    {
        $anosCursos = array_map('intval', array_filter([$v[59], $v[62], $v[65]], fn (?string $a): bool => $a !== null));
        $menorAno = $anosCursos === [] ? null : min($anosCursos);
        $areas = $this->tabelas->areasDePosGraduacao();
        foreach (self::CAMPOS_POS as [$pTipo, $pArea, $pAno]) {
            if ($v[$pTipo] === null) {
                continue;
            }
            // Campos 71, 74...: área da tabela; 72, 75...: ano não anterior ao menor ano de curso.
            if ($v[$pArea] === null || ! isset($areas[(int) $v[$pArea]])) {
                $this->recusa($pArea, 'Informe a área da pós-graduação pela tabela do Inep.');
            }
            $ano = (int) $v[$pAno];
            if ($v[$pAno] === null || $ano > $this->leiaute->ano || ($menorAno !== null && $ano < $menorAno)) {
                $this->recusa($pAno, 'Ano de conclusão da pós-graduação ausente ou anterior à conclusão da graduação.');
            }
        }
    }

    private function recusa(?int $campo, string $mensagem): void
    {
        foreach ($this->pendencias as $pendencia) {
            if ($campo !== null && $pendencia->campo === $campo && $pendencia->recusa()) {
                return;
            }
        }
        $this->pendencias[] = new Pendencia('30', $campo, $mensagem);
    }
}
