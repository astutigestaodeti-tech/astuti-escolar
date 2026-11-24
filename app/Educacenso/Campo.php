<?php

namespace App\Educacenso;

/**
 * Um campo de um registro do leiaute oficial, como o Inep o descreve na
 * planilha. Nada aqui é escrito à mão: vem de resources/educacenso/{ano}.
 */
final readonly class Campo
{
    /**
     * @param  list<string>|null  $opcoes  códigos aceitos, quando a planilha os lista
     * @param  list<array{n: ?string, texto: string, momento: ?string, status: ?string}>  $regras
     */
    public function __construct(
        public string $registro,
        public int $numero,
        public string $nome,
        public ?string $obrigatorio,
        public ?int $tamanho,
        public bool $tamanhoFixo,
        public ?string $tipo,
        public ?string $permitidos,
        public ?array $opcoes,
        public array $regras,
    ) {}

    /** Campo usado só no arquivo que o Inep devolve: deve ir vazio na importação. */
    public function somenteExportacao(): bool
    {
        return $this->tamanho === 0;
    }

    public function rotulo(): string
    {
        return "Registro {$this->registro}, campo {$this->numero} ({$this->nome})";
    }
}
