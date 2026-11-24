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
}
