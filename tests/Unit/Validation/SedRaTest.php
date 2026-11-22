<?php

namespace Tests\Unit\Validation;

use App\Validation\SedRa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SedRaTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'padrão' => ['000123456789-0/SP', null],
            'dígito X e UF minúscula' => ['000123456789-x/sp', null],
            'nove números' => ['123456789-1/MG', null],
            'curto e incompleto' => ['123', 'tem só 3 números antes do hífen (são de 9 a 12), falta o dígito verificador depois do hífen e falta a barra com a sigla do estado (/SP)'],
            'sem dígito e sem UF' => ['000123456789', 'falta o dígito verificador depois do hífen e falta a barra com a sigla do estado (/SP)'],
            'sem UF' => ['000123456789-0', 'falta a barra com a sigla do estado (/SP)'],
            'sem dígito' => ['000123456789-/SP', 'falta o dígito verificador depois do hífen'],
            'dígito duplo' => ['000123456789-12/SP', 'o dígito verificador depois do hífen é um só (número ou X)'],
            'UF inexistente' => ['000123456789-0/XX', '"XX" não é sigla de estado'],
            'números demais' => ['0001234567890123-0/SP', 'tem 16 números antes do hífen (o máximo é 12)'],
            'letras no meio' => ['12a3', 'use só números, o hífen, a barra e a sigla do estado, nessa ordem'],
            'só a UF' => ['/SP', 'faltam os números do RA e falta o dígito verificador depois do hífen'],
        ];
    }

    #[DataProvider('casos')]
    public function test_explica_o_que_esta_fora_do_padrao(string $ra, ?string $esperado): void
    {
        $this->assertSame($esperado, SedRa::problem($ra));
    }

    public function test_mensagem_completa_tem_ra_maiusculo_e_exemplo(): void
    {
        $mensagem = null;
        (new SedRa)->validate('ra', '123', function (string $texto) use (&$mensagem): void {
            $mensagem = $texto;
        });

        $this->assertStringStartsWith('RA fora do padrão da SED: ', $mensagem);
        $this->assertStringEndsWith('Exemplo: 000123456789-0/SP.', $mensagem);
    }
}
