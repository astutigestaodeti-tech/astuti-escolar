<?php

namespace Tests\Unit;

use App\Support\ClassAgeRule;
use Tests\TestCase;

class ClassAgeRuleTest extends TestCase
{
    public function test_etapas_reconhecidas_tem_idade_exata_na_data_de_corte(): void
    {
        $this->assertSame([4], ClassAgeRule::allowedAges('Educação Infantil', 'Pré I A'));
        $this->assertSame([4], ClassAgeRule::allowedAges('Pré-escola', 'PI-A'));
        $this->assertSame([4], ClassAgeRule::allowedAges('Educação Infantil', '4 anos'));
        $this->assertSame([5], ClassAgeRule::allowedAges('Educação Infantil', 'Pré II - B'));
        $this->assertSame([5], ClassAgeRule::allowedAges('Pré-escola', 'PII-A'));
        $this->assertSame([5], ClassAgeRule::allowedAges('Educação Infantil', '5 anos'));
        $this->assertSame([6], ClassAgeRule::allowedAges('Ensino Fundamental', '1º Ano A'));
        $this->assertSame([6], ClassAgeRule::allowedAges('Fundamental - Anos Iniciais', '1A'));
        $this->assertSame([8], ClassAgeRule::allowedAges('Ensino Fundamental', '3º Ano A'));
        $this->assertSame([14], ClassAgeRule::allowedAges('Ensino Fundamental - Anos Finais', '9º ano'));
    }

    public function test_codigo_ambiguo_ou_etapa_nao_reconhecida_nao_gera_alerta(): void
    {
        $this->assertSame([4, 5], ClassAgeRule::allowedAges('Educação Infantil', 'Pré-escola'));
        $this->assertSame([4, 5], ClassAgeRule::allowedAges('Pré-escola', 'Turma A'));
        $this->assertNull(ClassAgeRule::allowedAges('Educação Infantil', 'Maternal II'));
        $this->assertNull(ClassAgeRule::allowedAges('Ensino Fundamental', 'MULTI-1'));
        $this->assertNull(ClassAgeRule::allowedAges('Educação Infantil', '1A'));
        $this->assertNull(ClassAgeRule::allowedAges('EJA', '1º Ano A'));
        $this->assertNull(ClassAgeRule::allowedAges('EJA - Ensino Fundamental', '1º Ano A'));
        $this->assertNull(ClassAgeRule::allowedAges('Ensino Fundamental', '10º Ano'));
    }
}
