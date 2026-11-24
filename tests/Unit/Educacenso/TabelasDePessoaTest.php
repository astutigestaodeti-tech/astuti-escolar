<?php

namespace Tests\Unit\Educacenso;

use App\Educacenso\NomeDePessoa;
use App\Educacenso\Tabelas;
use Tests\TestCase;

class TabelasDePessoaTest extends TestCase
{
    public function test_municipios_vem_completos_da_tabela_oficial_e_sao_achados_pelo_nome_sem_acento(): void
    {
        $tabelas = Tabelas::do(2026);

        $this->assertSame(['codigo' => 3546801, 'nome' => 'Santa Isabel', 'uf' => 'SP'], $tabelas->municipio(3546801));
        $this->assertSame(3550308, $tabelas->municipioPorNome('SAO PAULO', 'sp'));
        $this->assertSame(1100015, $tabelas->municipioPorNome("Alta Floresta d'Oeste", 'RO'));
        $this->assertSame(3515103, $tabelas->municipioPorNome('Embu Guaçu', 'SP'));
        $this->assertSame(5300108, $tabelas->municipioPorNome('Brasília', 'DF'));
        $this->assertNull($tabelas->municipioPorNome('Monte Sereno', 'SP'));
        $this->assertNull($tabelas->municipioPorNome('Santa Isabel', 'RJ'));
        $this->assertNull($tabelas->municipioPorNome('Santa Isabel', null));

        $reflexao = new \ReflectionProperty($tabelas, 'municipios');
        $this->assertCount(5571, $reflexao->getValue($tabelas));
    }

    public function test_paises_e_povos_indigenas_vem_da_tabela_oficial(): void
    {
        $tabelas = Tabelas::do(2026);

        $this->assertSame('Brasil', $tabelas->paises()[76]);
        $this->assertSame('Paraguai', $tabelas->paises()[600]);
        $this->assertSame('Afeganistão', array_values($tabelas->paises())[0]);
        $this->assertSame('Aconã', $tabelas->povosIndigenas()[1]);
        $this->assertGreaterThan(300, count($tabelas->povosIndigenas()));
    }

    public function test_regra_do_inep_para_nome_de_pessoa_sem_cpf(): void
    {
        $this->assertSame([], NomeDePessoa::problemas('MARIA DA SILVA'));
        $this->assertSame(['deve ter nome e sobrenome'], NomeDePessoa::problemas('MARIA'));
        $this->assertSame(['as duas primeiras palavras precisam ter mais de uma letra (abreviação como "M." não é aceita)'], NomeDePessoa::problemas('M SILVA'));
        $this->assertContains('deve ter só letras e espaços', NomeDePessoa::problemas('MARIA 2 SILVA'));
        $this->assertSame(['não pode ter mais de quatro letras iguais seguidas'], NomeDePessoa::problemas('MARIAAAAA SILVA'));
        $this->assertSame([], NomeDePessoa::problemas('MARIAAAA SILVA'));
    }
}
