<?php

namespace Tests\Unit;

use App\Security\TotpQrCode;
use PHPUnit\Framework\TestCase;

final class TotpQrCodeTest extends TestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_endereco_otpauth_segue_o_padrao_dos_aplicativos(): void
    {
        $uri = (new TotpQrCode)->uri('pessoa+teste@astuti.invalid', self::SECRET);

        $this->assertSame(
            'otpauth://totp/Astuti%20Gest%C3%A3o%20Escolar:pessoa%2Bteste%40astuti.invalid?secret='.self::SECRET.'&issuer=Astuti%20Gest%C3%A3o%20Escolar&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }

    public function test_matriz_tem_os_tres_marcadores_de_canto(): void
    {
        $qr = new TotpQrCode;
        $matriz = $qr->modules($qr->uri('demo.monte-sereno@astuti.invalid', self::SECRET));
        $n = count($matriz);

        // Versao 10 (57 modulos) para um endereco deste tamanho com correcao M.
        $this->assertSame(57, $n);
        foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$linha, $coluna]) {
            for ($i = 0; $i < 7; $i++) {
                $this->assertTrue($matriz[$linha][$coluna + $i], 'borda superior do marcador');
                $this->assertTrue($matriz[$linha + 6][$coluna + $i], 'borda inferior do marcador');
                $this->assertTrue($matriz[$linha + $i][$coluna], 'borda esquerda do marcador');
            }
            $this->assertFalse($matriz[$linha + 1][$coluna + 1], 'anel claro do marcador');
            $this->assertTrue($matriz[$linha + 3][$coluna + 3], 'centro do marcador');
        }
    }

    public function test_svg_e_autocontido_com_margem_e_descricao(): void
    {
        $qr = new TotpQrCode;
        $svg = $qr->svg($qr->uri('demo@astuti.invalid', self::SECRET));
        $n = count($qr->modules($qr->uri('demo@astuti.invalid', self::SECRET)));

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.($n + 8).' '.($n + 8).'"', $svg);
        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringNotContainsString('href', $svg);
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertSame($svg, $qr->svg($qr->uri('demo@astuti.invalid', self::SECRET)), 'mesmo dado, mesmo desenho');
    }
}
