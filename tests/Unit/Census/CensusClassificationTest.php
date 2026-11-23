<?php

namespace Tests\Unit\Census;

use App\Census\CensusReadiness;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CensusClassificationTest extends TestCase
{
    public static function etapas(): array
    {
        return [
            'creche pelo nome' => ['Educação Infantil Berçário II', null, 'creche'],
            'maternal' => ['maternal_1', null, 'creche'],
            'pré-escola' => ['Pré-escola - Pré II', null, 'pre_escola'],
            'infantil pela idade, 3 anos' => ['Educação Infantil Turma A', 3, 'creche'],
            'infantil pela idade, 5 anos' => ['Educação Infantil Turma A', 5, 'pre_escola'],
            'infantil sem idade' => ['Educação Infantil Turma A', null, null],
            'ano escrito na turma' => ['Ensino Fundamental 3º Ano A', null, 'fundamental_iniciais'],
            'anos finais pelo número' => ['Ensino Fundamental 7º ano B', null, 'fundamental_finais'],
            'multisseriada' => ['Fundamental Anos Iniciais - 4º e 5º anos (multisseriada)', null, 'fundamental_iniciais'],
            'fundamental pela idade' => ['Ensino Fundamental Turma X', 12, 'fundamental_finais'],
            'EJA' => ['EJA Segmento I', null, 'eja'],
            'texto sem etapa' => ['Informe a etapa de ensino.', null, null],
        ];
    }

    #[DataProvider('etapas')]
    public function test_etapa_do_censo(string $texto, ?int $idade, ?string $esperado): void
    {
        $this->assertSame($esperado, CensusReadiness::stage($texto, $idade));
    }

    public function test_jornada_pelo_turno(): void
    {
        $this->assertSame('integral', CensusReadiness::shift('Integral'));
        $this->assertSame('parcial', CensusReadiness::shift('Manhã'));
        $this->assertSame('parcial', CensusReadiness::shift('vespertino'));
        $this->assertNull(CensusReadiness::shift('Turno'));
    }
}
