<?php

namespace App\Educacenso;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentCensus;
use App\Models\Tenant\StudentSpecialEducation;

/**
 * Registro 30 (pessoa física) de um aluno, com vínculo no registro 60.
 *
 * Os campos de escolaridade, formação e e-mail (56 a 110) pertencem a
 * gestores e profissionais (registros 40 e 50) e vão nulos para aluno.
 * O ID Inep (campo 4) só existe depois do retorno do Educacenso (fase 6).
 * Cada regra condicional cita o campo e a regra do leiaute do Inep.
 */
final class Registro30Aluno
{
    private const SEXO = ['male' => '1', 'female' => '2'];

    private const COR = ['nao_declarada' => '0', 'branca' => '1', 'preta' => '2', 'parda' => '3', 'amarela' => '4', 'indigena' => '5'];

    private const NACIONALIDADE = ['brasileira' => '1', 'naturalizada' => '2', 'estrangeira' => '3'];

    /** Campos que vêm da tela "Dados do Censo": sem ela, uma pendência só. */
    private const CAMPOS_DO_CENSO = [8, 9, 10, 13, 15, 16, 51, 52, 53, 54, 55];

    /** @var array<int, string|null> */
    public array $valores = [];

    /** @var list<Pendencia> */
    public array $pendencias = [];

    public function __construct(
        private readonly Leiaute $leiaute,
        private readonly Tabelas $tabelas,
        private readonly School $escola,
        private readonly Student $aluno,
        private readonly ?StudentCensus $censo,
        private readonly ?StudentSpecialEducation $especial,
        private readonly ?SchoolClassCensus $turma,
    ) {
        $this->montar();
    }

    public static function para(School $escola, Student $aluno, ?StudentCensus $censo, ?StudentSpecialEducation $especial, ?SchoolClassCensus $turma = null, ?int $ano = null): self
    {
        $ano ??= Tabelas::anoMaisRecente();

        return new self(Leiaute::do($ano), Tabelas::do($ano), $escola, $aluno, $censo, $especial, $turma);
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
        $a = $this->aluno;
        $c = $this->censo;
        $campos = StudentSpecialEducation::CENSUS_FIELDS;

        $v = array_fill(1, 110, null);
        $v[1] = '30';
        $v[2] = $this->escola->inep_code;
        $v[3] = CodigoCenso::dePessoa($a->id);
        $cpf = preg_replace('/\D/', '', (string) $a->cpf);
        $v[5] = $cpf === '' ? null : $cpf;
        $v[6] = Texto::normalizar($a->full_name);
        $v[7] = $a->birth_date?->format('d/m/Y');
        $v[8] = $c?->filiation === null ? null : (string) $c->filiation;
        $v[9] = $c?->filiation === 1 ? Texto::normalizar($c->parent1_name) : null;
        $v[10] = $c?->filiation === 1 ? Texto::normalizar($c->parent2_name) : null;
        $v[11] = self::SEXO[$a->sex ?? ''] ?? null;
        $v[12] = self::COR[$a->race_color ?? ''] ?? null;
        $v[13] = $v[12] === '5' && $c?->indigenous_people_code ? (string) $c->indigenous_people_code : null;
        $v[14] = self::NACIONALIDADE[$a->nationality ?? ''] ?? null;
        $v[15] = $c?->nationality_country_code ? (string) $c->nationality_country_code : null;
        $v[16] = $v[14] === '1' && $c?->birth_municipality_code ? (string) $c->birth_municipality_code : null;

        // Campos 17 a 28: deficiência, TEA e altas habilidades.
        $condicoes = $this->especial?->conditions ?? [];
        $v[17] = $condicoes === [] ? '0' : '1';
        if ($v[17] === '1') {
            foreach ($campos['conditions'] as $chave => $campo) {
                $v[$campo] = in_array($chave, $condicoes, true) ? '1' : '0';
            }
            $deficiencias = array_filter(range(18, 25), fn (int $campo): bool => $v[$campo] === '1');
            $v[26] = count($deficiencias) > 1 ? '1' : '0';
        }

        // Campos 29 a 35: transtornos que impactam a aprendizagem (há vínculo no registro 60).
        $transtornos = $this->especial?->learning_disorders ?? [];
        $v[29] = $transtornos === [] ? '0' : '1';
        if ($v[29] === '1') {
            foreach ($campos['learning_disorders'] as $chave => $campo) {
                $v[$campo] = in_array($chave, $transtornos, true) ? '1' : '0';
            }
        }

        // Campos 36 a 49: recursos para avaliações do Inep.
        $recursos = $this->especial?->exam_resources ?? [];
        if ($v[17] === '1' || $v[29] === '1') {
            foreach ($campos['exam_resources'] as $chave => $campo) {
                $v[$campo] = in_array($chave, $recursos, true) ? '1' : '0';
            }
            $v[49] = $recursos === [] ? '1' : '0';
        }

        $certidao = preg_replace('/\s/', '', (string) $a->birth_certificate);
        $v[50] = $certidao === '' ? null : strtoupper($certidao);
        $v[51] = $c?->residence_country_code ? (string) $c->residence_country_code : null;
        if ($v[51] === '76') {
            $cep = preg_replace('/\D/', '', (string) $a->postal_code);
            $v[52] = $cep === '' ? null : $cep;
            $v[53] = $c?->residence_municipality_code ? (string) $c->residence_municipality_code : null;
            $v[54] = match ($a->residence_zone) {
                'urban' => '1',
                'rural' => '2',
                default => null,
            };
            $v[55] = $c?->differentiated_location ? (string) $c->differentiated_location : null;
        }
        $this->valores = $v;

        // Regras primeiro: a mensagem delas explica onde corrigir; a conferência
        // genérica do campo só entra quando a regra não disse nada sobre ele.
        $this->conferirRegras($condicoes, $transtornos, $recursos);
        $this->conferirCampos();
    }

    private function conferirCampos(): void
    {
        foreach ($this->leiaute->campos('30') as $numero => $campo) {
            if ($numero >= 56 || ($this->censo === null && in_array($numero, self::CAMPOS_DO_CENSO, true))) {
                continue;
            }
            foreach (ValidadorDeCampo::conferir($campo, $this->valores[$numero]) as $problema) {
                $this->recusa($numero, "{$campo->nome}: {$problema}.");
            }
        }
    }

    /**
     * @param  list<string>  $condicoes
     * @param  list<string>  $transtornos
     * @param  list<string>  $recursos
     */
    private function conferirRegras(array $condicoes, array $transtornos, array $recursos): void
    {
        $v = $this->valores;
        if ($this->censo === null) {
            $this->recusa(null, 'Os dados do Censo do aluno ainda não foram preenchidos (filiação, municípios e residência).');
        }
        if (blank($this->escola->inep_code)) {
            $this->recusa(2, 'A escola não tem código Inep cadastrado.');
        }

        // Campos 6, 9 e 10, regra 1: nome de pessoa sem CPF.
        if ($v[5] === null) {
            foreach ([6 => $v[6], 9 => $v[10] === null ? $v[9] : null, 10 => $v[9] === null ? $v[10] : null] as $campo => $nome) {
                if ($nome !== null && ($problemas = NomeDePessoa::problemas($nome)) !== []) {
                    $this->recusa($campo, $this->leiaute->campo('30', $campo)->nome.': '.implode('; ', $problemas).'.');
                }
            }
        }
        // Campo 8, regra 2: filiação 1 exige pelo menos um nome.
        if ($v[8] === '1' && $v[9] === null && $v[10] === null) {
            $this->recusa(9, 'Filiação marcada como informada, mas sem nome.');
        }
        // Campo 13: povo indígena quando a cor/raça for indígena.
        if ($this->censo !== null && $v[12] === '5' && $v[13] === null) {
            $this->recusa(13, 'Informe o povo indígena nos dados do Censo do aluno.');
        }
        // Campo 15, regra 2, e campo 16.
        if ($this->censo !== null && $v[14] !== null && $v[15] === null) {
            $this->recusa(15, 'Informe o país de nacionalidade nos dados do Censo do aluno.');
        }
        if ($this->censo !== null && $v[14] === '1' && $v[16] === null) {
            $this->recusa(16, 'Informe o município de nascimento nos dados do Censo do aluno.');
        }
        // Campo 17, regra 2: aluno em classe especial ou turma de AEE tem de ter deficiência, TEA ou altas habilidades.
        if ($v[17] === '0' && ($this->turma?->special_class === true || $this->turma?->class_type === 5)) {
            $this->recusa(17, 'Aluno em classe especial ou AEE precisa ter a categoria de educação especial informada.');
        }
        // Campo 49, Anexo 4: "Nenhum" só vale se alguma condição marcada o admite; recursos compatíveis.
        if ($v[49] === '1') {
            $admiteNenhum = false;
            $porCondicao = $this->tabelas->recursosPorCondicao();
            foreach ([...array_map(fn ($c) => StudentSpecialEducation::CENSUS_FIELDS['conditions'][$c] ?? null, $condicoes),
                ...array_map(fn ($t) => StudentSpecialEducation::CENSUS_FIELDS['learning_disorders'][$t] ?? null, $transtornos)] as $campo) {
                if ($campo !== null && in_array('NENHUM', $porCondicao[Tabelas::chaveDeNome($this->leiaute->campo('30', $campo)->nome)] ?? [], true)) {
                    $admiteNenhum = true;
                }
            }
            // A tabela do Anexo 4 não traz "Nenhum" para cegueira, surdocegueira e transtornos.
            // Leitura literal, ainda sem confirmação do Inep: avisar em vez de recusar.
            if (! $admiteNenhum) {
                $this->pendencias[] = new Pendencia('30', 49, 'Pela tabela do Anexo 4, as categorias marcadas pedem pelo menos um recurso de prova. Confira com a escola.', Pendencia::AVISO);
            }
        }
        $recusados = RecursosDeProva::naoPermitidos($condicoes, $transtornos, $recursos, $this->leiaute->ano);
        if ($recusados !== []) {
            $this->recusa(36, 'Recursos de prova incompatíveis com as categorias marcadas (Anexo 4).');
        }
        // Campo 50, regras 2 e 3: certidão nova válida e ano de registro coerente.
        if ($v[50] !== null) {
            if (preg_match('/^\d{30}(\d{2}|XX)$/', $v[50]) !== 1) {
                $this->recusa(50, 'A certidão nova deve ter 32 caracteres: 30 números e mais 2 números ou "XX".');
            } else {
                $anoRegistro = (int) substr($v[50], 10, 4);
                $nascimento = (int) ($this->aluno->birth_date?->format('Y') ?? 0);
                if ($anoRegistro < $nascimento || $anoRegistro > (int) now()->format('Y')) {
                    $this->recusa(50, 'O ano de registro da certidão não combina com a data de nascimento.');
                }
            }
        }
        // Campos 51 a 55.
        if ($this->censo === null) {
            return;
        }
        if ($v[51] === null) {
            $this->recusa(51, 'Informe o país de residência nos dados do Censo do aluno.');
        } elseif ($v[51] === '76') {
            if ($v[54] === null) {
                $this->recusa(54, 'Informe a zona de residência (urbana ou rural) no endereço do aluno.');
            }
            if ($v[52] !== null && $v[53] === null) {
                $this->recusa(53, 'Com CEP informado, o município de residência é obrigatório.');
            }
            if ($v[55] === '1' && $v[54] === '1') {
                $this->recusa(55, 'Área de assentamento não combina com zona urbana.');
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
