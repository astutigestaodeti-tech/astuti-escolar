<?php

namespace Tests\Feature\Security;

use Tests\Support\TwoMunicipalityTestCase;

class SecurityHeadersTest extends TwoMunicipalityTestCase
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

    public function test_telas_publicas_recebem_cabecalhos_de_seguranca(): void
    {
        foreach (['/', '/login', '/demonstracao'] as $path) {
            $this->get($path)
                ->assertHeader('Content-Security-Policy', self::CSP)
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
                ->assertHeaderMissing('Strict-Transport-Security');
        }
    }

    public function test_rota_municipal_autenticada_recebe_mesmos_cabecalhos(): void
    {
        $user = $this->criarUsuario('cabecalhos@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->actingAs($user)->get('/m/municipio-a/escolas')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', self::CSP)
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_hsts_so_e_enviado_em_producao_e_https(): void
    {
        $this->get('https://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
        app()->detectEnvironment(fn () => 'production');
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_layout_usa_fontes_brasao_e_css_locais_sem_script_externo(): void
    {
        $this->assertFileExists(public_path('css/astuti.css'));
        $this->assertFileExists(public_path('images/brasao-monte-sereno.webp'));
        $this->assertNotEmpty(glob(public_path('fonts/*.woff2')));
        $this->get('/demonstracao')->assertOk()->assertSee('images/brasao-monte-sereno.webp');
    }

    public function test_erros_http_tambem_recebem_cabecalhos(): void
    {
        $this->get('/rota-que-nao-existe')->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', self::CSP);
    }
}
