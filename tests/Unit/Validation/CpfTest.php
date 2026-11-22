<?php

namespace Tests\Unit\Validation;

use App\Validation\Cpf;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    public function test_confere_digitos_e_formata(): void
    {
        $this->assertTrue(Cpf::isValid('529.982.247-25'));
        $this->assertTrue(Cpf::isValid('12345678909'));
        $this->assertFalse(Cpf::isValid('529.982.247-24'));
        $this->assertFalse(Cpf::isValid('000.000.000-00'));
        $this->assertFalse(Cpf::isValid('038.599.140-15'), 'CPF ficticio do kit nao tem digitos validos.');
        $this->assertSame('529.982.247-25', Cpf::normalize(' 529 982 247 25 '));
        $this->assertNull(Cpf::normalize('529.982.247-2a'));
        $this->assertNull(Cpf::normalize('1234'));
    }
}
