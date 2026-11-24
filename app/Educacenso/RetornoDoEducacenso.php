<?php

namespace App\Educacenso;

use App\Models\Tenant\InepIdentifier;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\StaffCensus;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Leitura do arquivo de exportação que o Educacenso devolve (mesmo leiaute
 * da importação, com os códigos do Inep preenchidos). Não grava nada: monta
 * a lista de vínculos a confirmar. Pessoa só é vinculada quando nome e data
 * de nascimento do arquivo conferem com o cadastro.
 */
final class RetornoDoEducacenso
{
    public const NOVO = 'novo';

    public const IGUAL = 'igual';

    public const CONFLITO = 'conflito';

    public const DIVERGENTE = 'divergente';

    public const NAO_ENCONTRADO = 'nao_encontrado';

    public const SITUACOES = [
        self::NOVO => 'Será gravado',
        self::IGUAL => 'Já estava gravado',
        self::CONFLITO => 'Conflito com código já gravado',
        self::DIVERGENTE => 'Nome ou nascimento não conferem',
        self::NAO_ENCONTRADO => 'Não encontrado no Astuti',
    ];

    public const TAMANHO_MAXIMO = 20 * 1024 * 1024;

    private const MAXIMO_DE_ERROS = 20;

    /** @var list<string> problemas de formato; com qualquer um, nada é gravado */
    public array $erros = [];

    /** @var list<array{tipo: string, entidade: ?string, codigo: string, atual: ?string, situacao: string, escola: string, descricao: string, motivo: ?string}> */
    public array $itens = [];

    /** @var list<string> códigos Inep de escolas do arquivo que não são desta rede */
    public array $escolasDeFora = [];

    public int $escolas = 0;

    private function __construct(public readonly int $ano, public readonly string $sha256) {}

    /** Lê um .txt ou um .zip com um .txt dentro. */
    public static function doArquivo(string $caminho, string $nomeOriginal, int $ano): self
    {
        $bytes = (string) file_get_contents($caminho);
        if (str_ends_with(strtolower($nomeOriginal), '.zip')) {
            $retorno = new self($ano, hash('sha256', $bytes));
            if (! class_exists(ZipArchive::class)) {
                $retorno->erros[] = 'Este servidor não abre .zip. Extraia o .txt e envie só ele.';

                return $retorno;
            }
            $zip = new ZipArchive;
            if ($zip->open($caminho) !== true) {
                $retorno->erros[] = 'Não consegui abrir o .zip. Confira se é o arquivo baixado do Educacenso.';

                return $retorno;
            }
            $txt = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nome = (string) $zip->getNameIndex($i);
                if (str_ends_with(strtolower($nome), '.txt') && ! str_contains($nome, '..')) {
                    $txt[] = $i;
                }
            }
            if (count($txt) !== 1 || ($zip->statIndex($txt[0])['size'] ?? PHP_INT_MAX) > self::TAMANHO_MAXIMO) {
                $zip->close();
                $retorno->erros[] = 'O .zip deve ter um único .txt de até 20 MB.';

                return $retorno;
            }
            $bytes = (string) $zip->getFromIndex($txt[0]);
            $zip->close();

            return self::doTexto($bytes, $ano, $retorno->sha256);
        }

        return self::doTexto($bytes, $ano, hash('sha256', $bytes));
    }

    public static function doTexto(string $bytes, int $ano, ?string $sha256 = null): self
    {
        $retorno = new self($ano, $sha256 ?? hash('sha256', $bytes));
        $retorno->ler($bytes);

        return $retorno;
    }

    /** @return array<string, int> quantidade de itens por situação */
    public function contagem(): array
    {
        $saida = array_fill_keys(array_keys(self::SITUACOES), 0);
        foreach ($this->itens as $item) {
            $saida[$item['situacao']]++;
        }

        return $saida;
    }

    /** @return list<array{tipo: string, entidade: string, codigo: string}> só o que será gravado */
    public function plano(): array
    {
        $plano = [];
        foreach ($this->itens as $item) {
            if ($item['situacao'] === self::NOVO) {
                $plano[] = ['tipo' => $item['tipo'], 'entidade' => (string) $item['entidade'], 'codigo' => $item['codigo']];
            }
        }

        return $plano;
    }

    private function ler(string $bytes): void
    {
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            $bytes = mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-1');
        }
        $leiaute = Leiaute::do($this->ano);
        $tamanhos = [];
        foreach ($leiaute->registros() as $registro) {
            $tamanhos[$registro] = count($leiaute->campos($registro));
        }

        /** @var array<string, array{turmas: array, pessoas: array, matriculas: array}> $blocos */
        $blocos = [];
        $escola = null;
        foreach (preg_split('/\r\n|\r|\n/', $bytes) as $numero => $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $campos = explode('|', $linha);
            $tipo = $campos[0];
            if ($tipo === '99') {
                break;
            }
            if (! isset($tamanhos[$tipo])) {
                $this->erro('Linha '.($numero + 1).': tipo de registro desconhecido.');

                continue;
            }
            // Aceita o pipe final, cuja presença ainda aguarda confirmação do Inep.
            if (count($campos) === $tamanhos[$tipo] + 1 && end($campos) === '') {
                array_pop($campos);
            }
            if (count($campos) !== $tamanhos[$tipo]) {
                $this->erro('Linha '.($numero + 1).": o registro {$tipo} tem ".count($campos)." campos; o leiaute {$this->ano} pede {$tamanhos[$tipo]}.");

                continue;
            }
            $c = fn (int $campo): string => trim($campos[$campo - 1]);
            if ($tipo === '00') {
                $escola = $c(2);
                $blocos[$escola] ??= ['turmas' => [], 'pessoas' => [], 'matriculas' => []];

                continue;
            }
            if ($escola === null || $c(2) !== $escola) {
                $this->erro('Linha '.($numero + 1).': registro fora do bloco da sua escola.');

                continue;
            }
            match ($tipo) {
                '20' => $blocos[$escola]['turmas'][$c(3)] = $c(4),
                '30' => $blocos[$escola]['pessoas'][$c(3)] = ['id' => $c(4), 'nome' => $c(6), 'nascimento' => $c(7)],
                '60' => $blocos[$escola]['matriculas'][] = ['pessoa' => $c(3), 'turma' => $c(5), 'matricula' => $c(7)],
                default => null,
            };
        }
        if ($blocos === [] && $this->erros === []) {
            $this->erro('O arquivo não tem nenhum registro 00 de escola.');
        }
        if ($this->erros !== []) {
            return;
        }

        $escolas = School::query()->whereIn('inep_code', array_map('strval', array_keys($blocos)))->get()->keyBy('inep_code');
        foreach ($blocos as $codigo => $bloco) {
            $codigo = (string) $codigo;
            $school = $escolas->get($codigo);
            if ($school === null) {
                $this->escolasDeFora[] = $codigo;

                continue;
            }
            $this->escolas++;
            $this->resolver($school, $bloco);
        }
    }

    /** @param  array{turmas: array<string, string>, pessoas: array<string, array>, matriculas: list<array>}  $bloco */
    private function resolver(School $escola, array $bloco): void
    {
        $chave = DB::raw("upper(left(replace(id::text, '-', ''), 20))");
        $turmas = SchoolClassCensus::query()->with('schoolClass:id,name,school_id')
            ->whereIn('census_code', array_map('strval', array_keys($bloco['turmas'])))
            ->get()->filter(fn (SchoolClassCensus $t): bool => $t->schoolClass?->school_id === $escola->id)->keyBy('census_code');
        $codigos = array_map('strval', array_keys($bloco['pessoas']));
        $alunos = Student::query()->whereIn($chave, $codigos)
            ->whereIn('id', StudentEnrollment::query()->where('school_id', $escola->id)->select('student_id'))
            ->get()->keyBy(fn (Student $s): string => CodigoCenso::dePessoa($s->id));
        $servidores = StaffMember::query()->where('school_id', $escola->id)->whereIn($chave, $codigos)
            ->get()->keyBy(fn (StaffMember $s): string => CodigoCenso::dePessoa($s->id));
        $nascimentoServidor = StaffCensus::query()->whereIn('staff_member_id', $servidores->pluck('id'))->pluck('birth_date', 'staff_member_id');
        $matriculas = StudentEnrollment::query()->whereIn('student_id', $alunos->pluck('id'))
            ->whereIn('school_class_id', $turmas->pluck('school_class_id'))->where('school_id', $escola->id)
            ->orderByDesc('started_at')->get();

        $entidades = [...$turmas->pluck('school_class_id'), ...$alunos->pluck('id'), ...$servidores->pluck('id'), ...$matriculas->pluck('id')];
        $gravados = InepIdentifier::query()->whereIn('entity_id', $entidades)->get()->keyBy(fn ($i): string => $i->kind.'|'.$i->entity_id);
        $codigosNoArquivo = [...array_values($bloco['turmas']), ...array_column($bloco['pessoas'], 'id'), ...array_column($bloco['matriculas'], 'matricula')];
        $usados = InepIdentifier::query()->whereIn('inep_code', array_values(array_filter($codigosNoArquivo, fn ($c) => $c !== '')))
            ->get()->groupBy(fn ($i): string => $i->kind.'|'.$i->inep_code);

        $registrar = function (string $tipo, ?string $entidade, string $codigo, string $descricao, ?string $motivo = null) use ($escola, $gravados, $usados): void {
            $atual = $entidade === null ? null : $gravados->get($tipo.'|'.$entidade)?->inep_code;
            $outro = $tipo !== InepIdentifier::STAFF_MEMBER && $entidade !== null
                && $usados->get($tipo.'|'.$codigo, collect())->contains(fn ($i): bool => $i->entity_id !== $entidade);
            $situacao = match (true) {
                $entidade === null => self::NAO_ENCONTRADO,
                $motivo !== null => self::DIVERGENTE,
                $atual === $codigo => self::IGUAL,
                $atual !== null || $outro => self::CONFLITO,
                default => self::NOVO,
            };
            if ($situacao === self::CONFLITO) {
                $motivo = $atual !== null ? 'Já tem o código '.$atual.' gravado.' : 'O código já está gravado para outro cadastro.';
            }
            $this->itens[] = [
                'tipo' => $tipo, 'entidade' => $entidade, 'codigo' => $codigo, 'atual' => $atual,
                'situacao' => $situacao, 'escola' => $escola->name, 'descricao' => $descricao, 'motivo' => $motivo,
            ];
        };

        $conferidas = [];
        foreach ($bloco['turmas'] as $codigoProprio => $codigoInep) {
            if (preg_match('/^\d{1,20}$/', $codigoInep) !== 1) {
                continue;
            }
            $turma = $turmas->get((string) $codigoProprio);
            $registrar(InepIdentifier::SCHOOL_CLASS, $turma?->school_class_id, $codigoInep, 'Turma '.($turma?->schoolClass?->name ?? $codigoProprio));
        }
        foreach ($bloco['pessoas'] as $codigoProprio => $pessoa) {
            if (preg_match('/^\d{12}$/', $pessoa['id']) !== 1) {
                continue;
            }
            $codigoProprio = (string) $codigoProprio;
            if ($aluno = $alunos->get($codigoProprio)) {
                $registrar(InepIdentifier::STUDENT, $aluno->id, $pessoa['id'], 'Aluno '.$aluno->full_name,
                    self::conferir($aluno->full_name, $aluno->birth_date?->format('d/m/Y'), $pessoa));
                $conferidas[$aluno->id] = $this->itens[array_key_last($this->itens)]['situacao'];
            } elseif ($servidor = $servidores->get($codigoProprio)) {
                $nascimento = $nascimentoServidor->get($servidor->id);
                $registrar(InepIdentifier::STAFF_MEMBER, $servidor->id, $pessoa['id'], 'Servidor '.$servidor->full_name,
                    self::conferir($servidor->full_name, $nascimento === null ? null : date('d/m/Y', strtotime((string) $nascimento)), $pessoa));
            } else {
                $registrar(InepIdentifier::STUDENT, null, $pessoa['id'], 'Pessoa '.$pessoa['nome']);
            }
        }
        foreach ($bloco['matriculas'] as $vinculo) {
            if (preg_match('/^\d{1,20}$/', $vinculo['matricula']) !== 1) {
                continue;
            }
            $aluno = $alunos->get($vinculo['pessoa']);
            $turma = $turmas->get($vinculo['turma']);
            $matricula = $aluno === null || $turma === null ? null
                : $matriculas->first(fn (StudentEnrollment $m): bool => $m->student_id === $aluno->id && $m->school_class_id === $turma->school_class_id);
            // A matrícula só é vinculada quando a pessoa do arquivo conferiu com o cadastro.
            $pessoaConferida = $aluno !== null && in_array($conferidas[$aluno->id] ?? null, [self::NOVO, self::IGUAL, self::CONFLITO], true);
            $registrar(InepIdentifier::ENROLLMENT, $matricula?->id, $vinculo['matricula'],
                'Matrícula de '.($aluno?->full_name ?? $bloco['pessoas'][$vinculo['pessoa']]['nome'] ?? 'pessoa não identificada').' em '.($turma?->schoolClass?->name ?? 'turma não identificada'),
                $matricula !== null && ! $pessoaConferida ? 'O aluno desta matrícula não conferiu com o cadastro.' : null);
        }
    }

    /**
     * Nome e nascimento do arquivo contra o cadastro. Devolve o motivo da
     * divergência ou null quando conferem.
     *
     * @param  array{nome: string, nascimento: string}  $pessoa
     */
    private static function conferir(string $nome, ?string $nascimento, array $pessoa): ?string
    {
        $limpo = fn (?string $texto): string => trim((string) preg_replace('/[^A-Z ]+/', '', (string) Texto::normalizar($texto)));
        if ($limpo($nome) !== $limpo($pessoa['nome'])) {
            return 'O nome no arquivo é diferente do cadastro.';
        }
        if ($nascimento === null) {
            return 'Sem data de nascimento no cadastro para conferir.';
        }

        return $nascimento === $pessoa['nascimento'] ? null : 'A data de nascimento no arquivo é diferente do cadastro.';
    }

    private function erro(string $mensagem): void
    {
        if (count($this->erros) < self::MAXIMO_DE_ERROS) {
            $this->erros[] = $mensagem;
        }
    }

    /** @return Collection<int, array> itens que pedem atenção, para a tela */
    public function problemas(): Collection
    {
        return collect($this->itens)->filter(fn (array $i): bool => in_array($i['situacao'], [self::CONFLITO, self::DIVERGENTE, self::NAO_ENCONTRADO], true))->values();
    }
}
