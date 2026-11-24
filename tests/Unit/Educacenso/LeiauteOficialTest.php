<?php

namespace Tests\Unit\Educacenso;

use PHPUnit\Framework\TestCase;

/**
 * O leiaute do Educacenso é extraído da planilha oficial do Inep por
 * scripts/educacenso/extrair-leiaute.py. Estes testes impedem que um arquivo
 * editado à mão ou incompleto chegue à exportação.
 */
class LeiauteOficialTest extends TestCase
{
    private const PASTA = __DIR__.'/../../../resources/educacenso/2026';

    private function leiaute(): array
    {
        return json_decode((string) file_get_contents(self::PASTA.'/leiaute.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_cada_registro_tem_os_campos_que_as_regras_gerais_do_inep_exigem(): void
    {
        $leiaute = $this->leiaute();
        $this->assertSame(2026, $leiaute['ano']);

        $exigidos = [];
        foreach ($leiaute['regras_gerais'] as $regra) {
            if (preg_match('/^O registro (\d{2}) deve ter (\d+) campos/', $regra['texto'], $m) === 1) {
                $exigidos[$m[1]] = (int) $m[2];
            }
        }
        $this->assertSame(['00' => 53, '10' => 187, '20' => 66, '30' => 110, '40' => 7, '50' => 38, '60' => 33], $exigidos);

        foreach ($exigidos as $chave => $total) {
            $registro = sprintf('%02d', $chave);
            $campos = $leiaute['registros'][$registro]['campos'];
            $this->assertCount($total, $campos, "registro {$registro}");
            $this->assertSame(range(1, $total), array_column($campos, 'campo'), "registro {$registro} em sequência");
            $this->assertSame($registro, $campos[0]['permitidos'], "campo 1 do registro {$registro} é o tipo de registro");
        }
    }

    public function test_campos_de_selecao_trazem_as_opcoes_da_planilha(): void
    {
        $campos = array_column($this->leiaute()['registros']['30']['campos'], null, 'campo');

        $this->assertSame('Cor/Raça', $campos[12]['nome']);
        $this->assertSame(['0', '1', '2', '3', '4', '5'], array_column($campos[12]['opcoes'], 'codigo'));
        $this->assertSame(['1', '2'], array_column($campos[11]['opcoes'], 'codigo'), 'sexo: 1 masculino, 2 feminino');
        $this->assertSame('s', $campos[6]['obrigatorio']);
        $this->assertSame(100, $campos[6]['tamanho']);
    }

    public function test_regras_gerais_do_arquivo_estao_presentes(): void
    {
        $textos = array_column($this->leiaute()['regras_gerais'], 'texto');

        $this->assertContains('Deve ser utilizado o padrão ISO-8859-1 de codificação de caracteres', $textos);
        $this->assertContains('Não pode haver letras minúsculas nem caracteres acentuados', $textos);
        $this->assertContains('Os arquivos devem ter no máximo 100 escolas', $textos);
    }

    public function test_tabelas_auxiliares_e_origem_registrada(): void
    {
        $origem = (string) file_get_contents(self::PASTA.'/ORIGEM.md');
        $this->assertSame(22, preg_match_all('/\| `[0-9a-f]{64}` \|/', $origem), 'leiaute + 21 tabelas com SHA-256');

        foreach (['municipios', 'distritos', 'paises', 'etapas', 'areas_conhecimento', 'ies', 'cursos_formacao_superior'] as $tabela) {
            $this->assertFileExists(self::PASTA."/tabelas/{$tabela}.csv");
        }
        $municipios = (string) file_get_contents(self::PASTA.'/tabelas/municipios.csv');
        $this->assertStringContainsString('35,São Paulo,3550308,São Paulo', $municipios);
        $this->assertSame(5571, preg_match_all('/^\d{2},[^,]+,\d{7},/m', $municipios));
    }
}
