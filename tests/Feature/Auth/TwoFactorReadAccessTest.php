<?php

namespace Tests\Feature\Auth;

use App\Security\Totp;
use App\Tenancy\TenantContext;
use Tests\Support\TwoMunicipalityTestCase;

class TwoFactorReadAccessTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_visitante_e_conta_sem_segundo_fator_conservam_as_consultas_atuais(): void
    {
        $this->get('/')->assertOk();
        $this->get('/m/municipio-a/escolas')->assertRedirect('/login');

        $user = $this->criarUsuario('consulta-sem-2fa@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        $this->actingAs($user)->get('/')->assertRedirect(route('schools.index', ['municipio' => 'municipio-a']));
        $this->get('/m/municipio-a/escolas')->assertOk()->assertSee('Unidade A');
    }

    public function test_conta_com_segundo_fator_nao_confirmado_nao_abre_rede_nem_consultas_por_url_direta(): void
    {
        $user = $this->criarUsuario('consulta-pendente@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->get('/')->assertRedirect(route('two-factor.challenge'));
        $this->get('/m/municipio-a/escolas')->assertRedirect(route('two-factor.challenge'));
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')->assertRedirect(route('two-factor.challenge'));
        $this->get('/seguranca/segundo-fator/desafio')->assertOk();
        $this->get('/seguranca/segundo-fator')->assertOk();

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_codigo_valido_libera_consulta_e_retorna_ao_endereco_original(): void
    {
        $user = $this->criarUsuario('consulta-confirmada@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $target = route('schools.index', ['municipio' => 'municipio-a']);
        $this->actingAs($user)->get($target)->assertRedirect(route('two-factor.challenge'));

        $code = app(Totp::class)->at(self::SECRET, now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/desafio', ['code' => $code])->assertRedirect($target);

        $this->get($target)->assertOk()->assertSee('Unidade A');
        $this->get('/')->assertRedirect(route('schools.index', ['municipio' => 'municipio-a']));
    }

    public function test_resposta_json_nao_redireciona_para_referer_externo(): void
    {
        $user = $this->criarUsuario('consulta-json-pendente@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)
            ->withHeader('Referer', 'https://externo.example.invalid/retorno')
            ->getJson('/m/municipio-a/escolas')
            ->assertForbidden()
            ->assertJsonPath('message', 'Confirme o segundo fator antes de consultar os dados.');

        $this->assertNull(session('url.intended'));
    }

    public function test_retorno_html_e_local_e_desafio_permite_sair(): void
    {
        $user = $this->criarUsuario('consulta-retorno-seguro@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        $target = '/m/municipio-a/escolas?pesquisa=teste';
        $this->actingAs($user)
            ->withHeader('Referer', 'https://externo.example.invalid/retorno')
            ->get($target)
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertSame($target, session('url.intended'));
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Sair da conta');
    }

    public function test_head_sem_codigo_nao_grava_referer_externo_no_retorno(): void
    {
        $user = $this->criarUsuario('consulta-head-pendente@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)
            ->withHeader('Referer', 'https://externo.example.invalid/retorno')
            ->call('HEAD', '/m/municipio-a/escolas')
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertNull(session('url.intended'));
    }

    public function test_municipio_inexistente_aguarda_segundo_fator_sem_consulta_antecipada(): void
    {
        $user = $this->criarUsuario('consulta-ordem-middleware@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)->get('/m/municipio-inexistente/escolas')
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_codigo_da_entrada_vale_durante_o_uso_e_vence_apos_doze_horas(): void
    {
        $user = $this->criarUsuario('consulta-expirada@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $user->forceFill([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user);
        $code = app(Totp::class)->at(self::SECRET, now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/desafio', ['code' => $code])->assertRedirect('/');

        $this->get('/m/municipio-a/escolas')->assertOk();

        // Depois de 15 minutos o uso normal continua sem pedir o código de novo.
        $this->travel(16)->minutes();
        $this->get('/m/municipio-a/escolas')->assertOk();

        // O teto de 12 horas vale mesmo para quem deixou o sistema aberto.
        $this->travel(12)->hours();
        $this->get('/m/municipio-a/escolas')->assertRedirect(route('two-factor.challenge'));
        $this->get('/')->assertRedirect(route('two-factor.challenge'));
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }
}
