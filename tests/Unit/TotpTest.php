<?php

namespace Tests\Unit;

use App\Security\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_vetor_publico_rfc_6238_com_seis_digitos(): void
    {
        $totp = new Totp;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        // RFC 6238: em 59 s, SHA-1 produz 94287082 (8 dígitos), ou 287082 (6).
        $this->assertSame('287082', $totp->at($secret, 59));
        $this->assertTrue($totp->verify($secret, '287082', 59));
        $this->assertFalse($totp->verify($secret, '287082', 150));
        $this->assertFalse($totp->verify($secret, '28708', 59));
    }

    public function test_secret_gerado_e_compativel_com_base32(): void
    {
        $totp = new Totp;
        $secret = $totp->generateSecret();

        $this->assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $secret);
        $this->assertTrue($totp->verify($secret, $totp->at($secret, 60), 60));
    }
}
