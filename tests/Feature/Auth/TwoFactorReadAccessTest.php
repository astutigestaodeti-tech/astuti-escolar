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

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Municipio A');
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
        $this->get('/')->assertOk()->assertSee('Municipio A');
    }

    public function test_confirmacao_expirada_volta_a_exigir_codigo_para_ler_dados_escolares(): void
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
        $this->travel(16)->minutes();

        $this->get('/m/municipio-a/escolas')->assertRedirect(route('two-factor.challenge'));
        $this->get('/')->assertRedirect(route('two-factor.challenge'));
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }
}
