<?php

namespace Tests\Unit\Validation;

use App\Validation\IdentityDocuments;
use PHPUnit\Framework\TestCase;

class IdentityDocumentsTest extends TestCase
{
    public function test_rg_guarda_so_numeros_e_x_e_formata_o_padrao_de_sp(): void
    {
        $this->assertSame('12345678X', IdentityDocuments::normalizeRg(' 12.345.678-x '));
        $this->assertTrue(IdentityDocuments::rgIsValid('12345678X'));
        $this->assertTrue(IdentityDocuments::rgIsValid('1234567'));
        $this->assertFalse(IdentityDocuments::rgIsValid('X1234'));
        $this->assertFalse(IdentityDocuments::rgIsValid('123'));
        $this->assertSame('12.345.678-X', IdentityDocuments::formatRg('12345678X'));
        $this->assertSame('1234567', IdentityDocuments::formatRg('1234567'));
        $this->assertNull(IdentityDocuments::normalizeRg('  '));
    }

    public function test_certidao_nova_tem_32_numeros_e_a_antiga_e_texto(): void
    {
        $nova = IdentityDocuments::normalizeBirthCertificate('123456.01.55.2019.1.00012.034.0001234-56');
        $this->assertSame('12345601552019100012034000123456', $nova);
        $this->assertTrue(IdentityDocuments::birthCertificateIsValid($nova));
        $this->assertSame('123456 01 55 2019 1 00012 034 0001234 56', IdentityDocuments::formatBirthCertificate($nova));

        $this->assertFalse(IdentityDocuments::birthCertificateIsValid((string) IdentityDocuments::normalizeBirthCertificate('1234 5678')));
        $this->assertTrue(IdentityDocuments::birthCertificateIsValid('Livro A-12, folha 34, termo 5678'));
        $this->assertFalse(IdentityDocuments::birthCertificateIsValid('<script>'));
    }
}
