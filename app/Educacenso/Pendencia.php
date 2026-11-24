<?php

namespace App\Educacenso;

/** Algo que impede ou compromete o envio de um registro ao Educacenso. */
final readonly class Pendencia
{
    /** O Educacenso recusa a escola inteira por causa disso. */
    public const RECUSA = 'recusa';

    /** Depende de outro cadastro (outro registro) ou só o Inep confere: avisar. */
    public const AVISO = 'aviso';

    public function __construct(
        public string $registro,
        public ?int $campo,
        public string $mensagem,
        public string $gravidade = self::RECUSA,
    ) {}

    public function recusa(): bool
    {
        return $this->gravidade === self::RECUSA;
    }
}
