<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__teste_proxy', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
        ]));
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_proxy_autorizado_preserva_ip_e_https_sem_confiar_no_host_encaminhado(): void
    {
        app()->detectEnvironment(fn () => 'production');
        TrustProxies::at(['127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.24',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-Host' => 'dominio-falso.invalid',
            ])
            ->get('http://escolar.example.test/__teste_proxy')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.24')
            ->assertJsonPath('secure', true)
            ->assertJsonPath('host', 'escolar.example.test')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_visitante_direto_nao_consegue_forjar_https_nem_ip(): void
    {
        app()->detectEnvironment(fn () => 'production');
        TrustProxies::at(['127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.44'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.9',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'dominio-falso.invalid',
            ])
            ->get('http://escolar.example.test/__teste_proxy')
            ->assertOk()
            ->assertJsonPath('ip', '198.51.100.44')
            ->assertJsonPath('secure', false)
            ->assertJsonPath('host', 'escolar.example.test')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_encaminhado_em_desenvolvimento_nao_ativa_hsts(): void
    {
        TrustProxies::at(['127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://escolar.example.test/__teste_proxy')
            ->assertOk()
            ->assertJsonPath('secure', true)
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_header_forwarded_padrao_nao_e_aceito_mesmo_de_proxy_autorizado(): void
    {
        TrustProxies::at(['127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Forwarded', 'for=203.0.113.90;proto=https;host=dominio-falso.invalid')
            ->get('http://escolar.example.test/__teste_proxy')
            ->assertOk()
            ->assertJsonPath('ip', '127.0.0.1')
            ->assertJsonPath('secure', false)
            ->assertJsonPath('host', 'escolar.example.test');
    }
}
