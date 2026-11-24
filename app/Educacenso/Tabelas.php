<?php

namespace App\Educacenso;

use InvalidArgumentException;

/**
 * Tabelas auxiliares e anexos do Educacenso de um ano, lidos dos arquivos
 * gerados a partir dos documentos oficiais (resources/educacenso/{ano}).
 */
final class Tabelas
{
    /** @var array<int, self> */
    private static array $carregadas = [];

    /** @var array<int, array{codigo: int, nome: string, agregada: int, nome_agregada: string}> */
    private array $etapas = [];

    /** @var array<int, array{codigo: int, nome: string, area: string, subarea: string}>|null */
    private ?array $atividades = null;

    /** @var array<int, array{codigo: int, nome: string, uf: string}>|null */
    private ?array $municipios = null;

    /** @var array<string, int>|null chave "UF|NOME NORMALIZADO" => código */
    private ?array $municipiosPorNome = null;

    /** @var array<int, array<string, string>>|null */
    private ?array $distritos = null;

    /** @var array<int, array<string, string>>|null */
    private ?array $orgaosRegionais = null;

    /** @var array<int, string>|null */
    private ?array $paises = null;

    /** @var array<int, string>|null */
    private ?array $povos = null;

    /** @var array<string, list<string>>|null */
    private ?array $recursos = null;

    /** @var array<string, array{codigo: string, nome: string, grau: string, area: string}>|null */
    private ?array $cursos = null;

    /** @var array<int, array{codigo: int, nome: string, municipio: int|null, ativa: bool}>|null */
    private ?array $ies = null;

    /** @param  array<string, mixed>  $dados */
    private function __construct(public readonly int $ano, private readonly array $dados)
    {
        foreach ($dados['etapas'] as $etapa) {
            $this->etapas[(int) $etapa['codigo']] = $etapa;
        }
    }

    public static function do(int $ano): self
    {
        if (isset(self::$carregadas[$ano])) {
            return self::$carregadas[$ano];
        }
        $arquivo = resource_path("educacenso/{$ano}/tabelas.json");
        if (! is_file($arquivo)) {
            throw new InvalidArgumentException("Tabelas do Educacenso {$ano} não disponíveis.");
        }

        return self::$carregadas[$ano] = new self($ano, json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR));
    }

    /** Ano do leiaute mais recente disponível no sistema. */
    public static function anoMaisRecente(): int
    {
        $anos = array_map('intval', array_filter(
            array_map('basename', glob(resource_path('educacenso/*'), GLOB_ONLYDIR) ?: []),
            fn (string $pasta): bool => preg_match('/^20\d\d$/', $pasta) === 1 && is_file(resource_path("educacenso/{$pasta}/tabelas.json")),
        ));
        if ($anos === []) {
            throw new InvalidArgumentException('Nenhum leiaute do Educacenso disponível.');
        }

        return max($anos);
    }

    /** @return array{codigo: int, nome: string, agregada: int, nome_agregada: string}|null */
    public function etapa(int $codigo): ?array
    {
        return $this->etapas[$codigo] ?? null;
    }

    /** @return array<int, array{codigo: int, nome: string, agregada: int, nome_agregada: string}> */
    public function etapas(): array
    {
        return $this->etapas;
    }

    /** Áreas do conhecimento que podem ser 1 na etapa (Tabela de Regras de Áreas do Conhecimento). */
    public function areasPermitidas(int $etapa): array
    {
        return $this->dados['areas_por_etapa'][(string) $etapa] ?? [];
    }

    /** @return array<int, int> código da área => número do campo no registro 20 */
    public function camposDasAreas(): array
    {
        $saida = [];
        foreach ($this->dados['areas_campo'] as $codigo => $campo) {
            $saida[(int) $codigo] = (int) $campo;
        }

        return $saida;
    }

    /** @return list<int> formas de organização permitidas para a etapa (Anexo 6) */
    public function organizacoes(int $etapa): array
    {
        return $this->dados['organizacao_por_etapa'][(string) $etapa]['formas'] ?? [];
    }

    /** Turma de formação por alternância permitida para a etapa (Anexo 6). */
    public function alternanciaPermitida(int $etapa): bool
    {
        return (bool) ($this->dados['organizacao_por_etapa'][(string) $etapa]['alternancia'] ?? false);
    }

    /** Combinação tipo de mediação x tipo de turma x etapa do Anexo 7. */
    public function combinacaoPermitida(int $mediacao, int $tipoTurma, ?int $etapa): bool
    {
        foreach ($this->dados['mediacao_tipo_etapa'] as $linha) {
            if ($linha['mediacao'] !== $mediacao || $linha['tipo_turma'] !== $tipoTurma) {
                continue;
            }
            if ($etapa === null ? $linha['etapas'] === [] && $linha['observacao'] === null : in_array($etapa, $linha['etapas'], true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array{codigo: int, nome: string, area: string, subarea: string}> */
    public function atividadesComplementares(): array
    {
        if ($this->atividades !== null) {
            return $this->atividades;
        }
        $this->atividades = [];
        $arquivo = fopen(resource_path("educacenso/{$this->ano}/tabelas/atividade_complementar.csv"), 'r');
        $area = $subarea = '';
        while (($linha = fgetcsv($arquivo, escape: '')) !== false) {
            if (! isset($linha[2]) || preg_match('/^\d{5}$/', $linha[2]) !== 1) {
                continue;
            }
            $area = $linha[0] !== '' ? $linha[0] : $area;
            $subarea = ($linha[1] ?? '') !== '' ? $linha[1] : $subarea;
            $this->atividades[(int) $linha[2]] = ['codigo' => (int) $linha[2], 'nome' => trim($linha[3] ?? ''), 'area' => $area, 'subarea' => $subarea];
        }
        fclose($arquivo);

        return $this->atividades;
    }

    /** @return array{codigo: int, nome: string, uf: string}|null */
    public function municipio(int $codigo): ?array
    {
        $this->carregarMunicipios();

        return $this->municipios[$codigo] ?? null;
    }

    /**
     * Código do município pelo nome e pela sigla da UF, sem distinguir
     * maiúsculas, acentos ou apóstrofos. Nome ambíguo ou inexistente: null.
     */
    public function municipioPorNome(?string $nome, ?string $uf): ?int
    {
        if ($nome === null || $uf === null || trim($nome) === '' || trim($uf) === '') {
            return null;
        }
        $this->carregarMunicipios();

        return $this->municipiosPorNome[strtoupper(trim($uf)).'|'.self::chaveDeNome($nome)] ?? null;
    }

    /** @return array<string, string> código de importação => nome do distrito */
    public function distritos(int $municipio): array
    {
        if ($this->distritos === null) {
            $this->distritos = [];
            foreach ($this->linhasComCodigo('distritos.csv', 2) as $linha) {
                if (isset($linha[6]) && preg_match('/^\\d{2}$/', trim($linha[6])) === 1) {
                    $this->distritos[(int) $linha[2]][trim($linha[6])] = trim($linha[5]);
                }
            }
        }

        return $this->distritos[$municipio] ?? [];
    }

    /** @return array<string, string> código => nome do órgão regional da UF */
    public function orgaosRegionais(string $uf): array
    {
        if ($this->orgaosRegionais === null) {
            $this->orgaosRegionais = [];
            foreach ($this->linhasComCodigo('orgaos_regionais.csv') as $linha) {
                if (isset($linha[5]) && preg_match('/^[A-Z0-9]{1,5}$/', trim($linha[4])) === 1) {
                    $this->orgaosRegionais[(int) $linha[0]][trim($linha[4])] = trim($linha[5]);
                }
            }
        }
        $codigo = array_search(strtoupper(trim($uf)), self::SIGLAS_UF, true);

        return $codigo === false ? [] : ($this->orgaosRegionais[$codigo] ?? []);
    }

    /** @return array<int, string> código => nome do país */
    public function paises(): array
    {
        if ($this->paises === null) {
            $this->paises = [];
            foreach ($this->linhasComCodigo('paises.csv') as $linha) {
                $this->paises[(int) $linha[0]] = trim($linha[1]);
            }
            uasort($this->paises, fn (string $a, string $b): int => strcmp(self::chaveDeNome($a), self::chaveDeNome($b)));
        }

        return $this->paises;
    }

    /** @return array<string, string> código de importação => língua indígena */
    public function linguasIndigenas(): array
    {
        $linguas = [];
        foreach ($this->linhasComCodigo('linguas_indigenas.csv', 4) as $linha) {
            if (isset($linha[5])) {
                $linguas[str_pad(trim($linha[4]), 5, '0', STR_PAD_LEFT)] = trim($linha[5]);
            }
        }

        return $linguas;
    }

    /** @return array<int, string> código => nome do povo indígena */
    public function povosIndigenas(): array
    {
        if ($this->povos === null) {
            $this->povos = [];
            foreach ($this->linhasComCodigo('povos_indigenas.csv') as $linha) {
                $this->povos[(int) $linha[0]] = trim($linha[1]);
            }
        }

        return $this->povos;
    }

    /**
     * Recursos de prova permitidos para cada deficiência, TEA ou transtorno
     * (Anexo 4: tabelas "Deficiência x Recursos" e "Transtorno x Recursos").
     * Chaves e valores com o nome normalizado (maiúsculas, sem acentos).
     *
     * @return array<string, list<string>>
     */
    public function recursosPorCondicao(): array
    {
        if ($this->recursos !== null) {
            return $this->recursos;
        }
        $this->recursos = [];
        foreach (['deficiencia_recurso.csv', 'transtorno_recurso.csv'] as $arquivo) {
            $handle = fopen(resource_path("educacenso/{$this->ano}/tabelas/{$arquivo}"), 'r');
            $dados = false;
            while (($linha = fgetcsv($handle, escape: '')) !== false) {
                if (! $dados) {
                    $dados = in_array(strtoupper(trim($linha[0] ?? '')), ['DEFICIENCIA', 'TRANSTORNO'], true);

                    continue;
                }
                if (count($linha) < 2 || trim($linha[0]) === '') {
                    continue;
                }
                $this->recursos[self::chaveDeNome($linha[0])][] = self::chaveDeNome($linha[1]);
            }
            fclose($handle);
        }

        return $this->recursos;
    }

    /**
     * Cursos de formação superior (campos 58, 61 e 64 do registro 30), com o
     * código alfanumérico do Inep (ex.: 0113P011).
     *
     * @return array<string, array{codigo: string, nome: string, grau: string, area: string}>
     */
    public function cursosSuperiores(): array
    {
        if ($this->cursos === null) {
            $this->cursos = [];
            $handle = fopen(resource_path("educacenso/{$this->ano}/tabelas/cursos_formacao_superior.csv"), 'r');
            while (($linha = fgetcsv($handle, escape: '')) !== false) {
                $codigo = strtoupper(trim($linha[6] ?? ''));
                if (preg_match('/^\d{4}[A-Z]\d{2,3}$/', $codigo) === 1) {
                    $this->cursos[$codigo] = ['codigo' => $codigo, 'nome' => trim($linha[7] ?? ''), 'grau' => trim($linha[8] ?? ''), 'area' => trim($linha[1] ?? '')];
                }
            }
            fclose($handle);
        }

        return $this->cursos;
    }

    /** Instituição de educação superior pelo código (campos 60, 63 e 66). */
    public function ies(int $codigo): ?array
    {
        if ($this->ies === null) {
            $this->ies = [];
            foreach ($this->linhasComCodigo('ies.csv') as $linha) {
                $this->ies[(int) $linha[0]] = [
                    'codigo' => (int) $linha[0],
                    'nome' => trim($linha[1] ?? ''),
                    'municipio' => preg_match('/^\d{7}$/', trim($linha[4] ?? '')) === 1 ? (int) $linha[4] : null,
                    'ativa' => Texto::normalizar(trim($linha[2] ?? '')) === 'ATIVA',
                ];
            }
        }

        return $this->ies[$codigo] ?? null;
    }

    /** @return array<int, string> código => área do conhecimento ou componente curricular */
    public function areasDoConhecimento(): array
    {
        $saida = [];
        foreach ($this->linhasComCodigo('areas_conhecimento.csv') as $linha) {
            $saida[(int) $linha[0]] = trim($linha[1] ?? '');
        }

        return $saida;
    }

    /** @return array<int, string> código => área geral da pós-graduação */
    public function areasDePosGraduacao(): array
    {
        $saida = [];
        foreach ($this->linhasComCodigo('areas_pos_graduacao.csv') as $linha) {
            $saida[(int) $linha[0]] = trim($linha[1] ?? '');
        }

        return $saida;
    }

    /** Nome normalizado usado para comparar rótulos das tabelas com os do leiaute. */
    public static function chaveDeNome(string $nome): string
    {
        $texto = (string) Texto::normalizar(str_replace(["'", '`', '´', '’', '-'], ' ', $nome));

        return (string) preg_replace('/\s+/', ' ', trim($texto));
    }

    private function carregarMunicipios(): void
    {
        if ($this->municipios !== null) {
            return;
        }
        $siglas = self::SIGLAS_UF;
        $this->municipios = $this->municipiosPorNome = [];
        $repetidos = [];
        foreach ($this->linhasComCodigo('municipios.csv', 2) as $linha) {
            $uf = $siglas[(int) $linha[0]] ?? null;
            if ($uf === null) {
                continue;
            }
            $codigo = (int) $linha[2];
            $this->municipios[$codigo] = ['codigo' => $codigo, 'nome' => trim($linha[3]), 'uf' => $uf];
            $chave = $uf.'|'.self::chaveDeNome($linha[3]);
            if (isset($this->municipiosPorNome[$chave])) {
                $repetidos[$chave] = true;
            }
            $this->municipiosPorNome[$chave] = $codigo;
        }
        foreach (array_keys($repetidos) as $chave) {
            unset($this->municipiosPorNome[$chave]);
        }
    }

    /**
     * Linhas de dados de uma tabela auxiliar: as que têm código numérico na
     * coluna indicada (o cabeçalho institucional do Inep fica de fora).
     *
     * @return iterable<int, list<string>>
     */
    private function linhasComCodigo(string $arquivo, int $colunaDoCodigo = 0): iterable
    {
        $handle = fopen(resource_path("educacenso/{$this->ano}/tabelas/{$arquivo}"), 'r');
        try {
            while (($linha = fgetcsv($handle, escape: '')) !== false) {
                if (isset($linha[$colunaDoCodigo]) && preg_match('/^\d+$/', trim($linha[$colunaDoCodigo])) === 1) {
                    yield $linha;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /** Código IBGE da UF => sigla. */
    public const SIGLAS_UF = [
        11 => 'RO', 12 => 'AC', 13 => 'AM', 14 => 'RR', 15 => 'PA', 16 => 'AP', 17 => 'TO',
        21 => 'MA', 22 => 'PI', 23 => 'CE', 24 => 'RN', 25 => 'PB', 26 => 'PE', 27 => 'AL', 28 => 'SE', 29 => 'BA',
        31 => 'MG', 32 => 'ES', 33 => 'RJ', 35 => 'SP',
        41 => 'PR', 42 => 'SC', 43 => 'RS',
        50 => 'MS', 51 => 'MT', 52 => 'GO', 53 => 'DF',
    ];
}
