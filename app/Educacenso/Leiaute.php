<?php

namespace App\Educacenso;

use InvalidArgumentException;

/**
 * Leiaute de importação e exportação da Matrícula Inicial de um ano, lido do
 * arquivo gerado a partir da planilha oficial do Inep.
 */
final class Leiaute
{
    /** @var array<int, self> */
    private static array $carregados = [];

    /** @var array<string, array<int, Campo>> */
    private array $campos = [];

    /** @param  array<string, mixed>  $dados */
    private function __construct(public readonly int $ano, private readonly array $dados)
    {
        foreach ($dados['registros'] as $registro => $conteudo) {
            $registro = sprintf('%02d', $registro);
            foreach ($conteudo['campos'] as $campo) {
                $this->campos[$registro][(int) $campo['campo']] = new Campo(
                    registro: $registro,
                    numero: (int) $campo['campo'],
                    nome: (string) $campo['nome'],
                    obrigatorio: $campo['obrigatorio'],
                    tamanho: is_int($campo['tamanho']) ? $campo['tamanho'] : null,
                    tamanhoFixo: (bool) $campo['tamanho_fixo'],
                    tipo: $campo['tipo'],
                    permitidos: $campo['permitidos'],
                    opcoes: self::opcoes($campo),
                    regras: $campo['regras'],
                );
            }
        }
    }

    public static function do(int $ano): self
    {
        if (isset(self::$carregados[$ano])) {
            return self::$carregados[$ano];
        }
        $arquivo = resource_path("educacenso/{$ano}/leiaute.json");
        if (! is_file($arquivo)) {
            throw new InvalidArgumentException("Leiaute do Educacenso {$ano} não disponível.");
        }
        $dados = json_decode((string) file_get_contents($arquivo), true, flags: JSON_THROW_ON_ERROR);
        if ((int) $dados['ano'] !== $ano) {
            throw new InvalidArgumentException("O arquivo do leiaute {$ano} declara outro ano.");
        }

        return self::$carregados[$ano] = new self($ano, $dados);
    }

    /** @return list<string> */
    public function registros(): array
    {
        return array_keys($this->campos);
    }

    /** @return array<int, Campo> campos do registro, numerados a partir de 1 */
    public function campos(string $registro): array
    {
        return $this->campos[$registro] ?? throw new InvalidArgumentException("Registro {$registro} inexistente no leiaute {$this->ano}.");
    }

    public function campo(string $registro, int $numero): Campo
    {
        return $this->campos($registro)[$numero] ?? throw new InvalidArgumentException("Campo {$numero} inexistente no registro {$registro}.");
    }

    /** @return list<array{n: int, texto: string, momento: ?string, status: ?string}> */
    public function regrasGerais(): array
    {
        return $this->dados['regras_gerais'];
    }

    /**
     * Códigos aceitos quando a planilha lista as opções ("1-Urbana", "2-Rural"),
     * em qualquer tipo de campo. Campos que remetem a tabela auxiliar não têm lista aqui.
     *
     * @param  array<string, mixed>  $campo
     * @return list<string>|null
     */
    private static function opcoes(array $campo): ?array
    {
        if (is_array($campo['opcoes'] ?? null)) {
            return array_values(array_map(fn (array $opcao): string => (string) $opcao['codigo'], $campo['opcoes']));
        }
        $linhas = array_values(array_filter(array_map('trim', explode("\n", (string) $campo['permitidos']))));
        if ($linhas === [] || count($linhas) < 2) {
            return null;
        }
        $codigos = [];
        foreach ($linhas as $linha) {
            if (preg_match('/^(\d+)\s*[-–]\s*\S/u', $linha, $m) !== 1) {
                return null;
            }
            $codigos[] = $m[1];
        }

        return $codigos;
    }
}
