<?php

namespace Tests\Unit;

use App\Support\TrustedProxyAddresses;
use InvalidArgumentException;
use Tests\TestCase;

class TrustedProxyAddressesTest extends TestCase
{
    public function test_configuracao_inicial_nao_confia_em_proxies(): void
    {
        $this->assertSame([], config('trustedproxy.proxies'));
    }

    public function test_lista_de_ips_e_redes_e_validada_e_normalizada(): void
    {
        $this->assertTrue(class_exists(TrustedProxyAddresses::class));

        $this->assertSame(
            ['127.0.0.1', '10.10.0.0/16', '::1', '2001:db8::/32', '10.0.0.0/8', '2001:db8::/16'],
            TrustedProxyAddresses::parse(' 127.0.0.1, 10.10.0.0/16, ::1,2001:db8::/32,127.0.0.1,10.0.0.0/8, 2001:db8::/16, '),
        );
        $this->assertSame([], TrustedProxyAddresses::parse(null));
        $this->assertSame([], TrustedProxyAddresses::parse(' , , '));
    }

    public function test_curinga_e_enderecos_invalidos_nao_sao_aceitos(): void
    {
        $this->assertTrue(class_exists(TrustedProxyAddresses::class));

        foreach (['*', 'all', 'private_ranges', '999.999.9.9', '10.0.0.0/33', '::1/129', '127.0.0.1/', '127.0.0.1/-1', '0.0.0.0/0', '::/0', '10.0.0.0/7', '2001:db8::/15'] as $invalid) {
            try {
                TrustedProxyAddresses::parse($invalid);
                $this->fail('Uma entrada invalida foi aceita.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
