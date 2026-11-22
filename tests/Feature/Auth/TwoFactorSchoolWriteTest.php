<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\RequireConfirmedTwoFactor;
use App\Security\Totp;
use Illuminate\Support\Facades\Route;
use Tests\Support\TwoMunicipalityTestCase;

class TwoFactorSchoolWriteTest extends TwoMunicipalityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/_teste/escrita-escolar', fn () => response('gravado'))
            ->middleware(['web', 'auth', RequireConfirmedTwoFactor::class]);
    }

    public function test_senha_sozinha_nao_libera_gravacao_escolar(): void
    {
        $user = $this->criarUsuario('dois-fatores@example.invalid');

        $this->actingAs($user)->post('/_teste/escrita-escolar')->assertForbidden();
    }

    public function test_codigo_valido_ativa_escrita_apenas_apos_confirmacao(): void
    {
        $user = $this->criarUsuario('codigo-valido@example.invalid');
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->post('/_teste/escrita-escolar')->assertForbidden();

        $code = app(Totp::class)->at('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/desafio', ['code' => $code])->assertRedirect('/');
        $this->post('/_teste/escrita-escolar')->assertOk()->assertSee('gravado');
    }

    public function test_codigo_errado_e_sessao_expirada_nao_liberam_gravacao(): void
    {
        $user = $this->criarUsuario('codigo-errado@example.invalid');
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->post('/seguranca/segundo-fator/desafio', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->post('/_teste/escrita-escolar')->assertForbidden();

        $code = app(Totp::class)->at('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/desafio', ['code' => $code])->assertRedirect('/');
        $this->travel(16)->minutes();
        $this->post('/_teste/escrita-escolar')->assertOk();
        $this->travel(12)->hours();
        $this->post('/_teste/escrita-escolar')->assertForbidden();
    }

    public function test_logout_apaga_confirmacao_do_segundo_fator(): void
    {
        $user = $this->criarUsuario('saida-dois-fatores@example.invalid');
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user);
        $code = app(Totp::class)->at('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/desafio', ['code' => $code])->assertRedirect('/');
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/_teste/escrita-escolar')->assertRedirect('/login');
    }

    public function test_segundo_fator_desativado_rejeita_sessao_antiga(): void
    {
        $user = $this->criarUsuario('fator-revogado@example.invalid');
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user);
        $code = app(Totp::class)->at('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/desafio', ['code' => $code])->assertRedirect('/');
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->post('/_teste/escrita-escolar')->assertForbidden();
    }

    public function test_configuracao_exige_senha_e_o_codigo_antes_de_ativar(): void
    {
        $user = $this->criarUsuario('configurar-fator@example.invalid');

        $this->actingAs($user)
            ->post('/seguranca/segundo-fator/iniciar', ['password' => 'errada'])
            ->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->two_factor_secret);

        $this->post('/seguranca/segundo-fator/iniciar', ['password' => 'SenhaForteDeTeste123!'])
            ->assertRedirect('/seguranca/segundo-fator');
        $user->refresh();
        $this->assertNotEmpty($user->two_factor_pending_secret);
        $this->assertNull($user->two_factor_secret);

        $code = app(Totp::class)->at($user->two_factor_pending_secret, now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/confirmar', ['code' => $code])
            ->assertRedirect('/seguranca/segundo-fator');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->post('/_teste/escrita-escolar')->assertOk();
    }

    public function test_nao_existe_cadastro_publico_de_contas(): void
    {
        $this->post('/register')->assertNotFound();
    }

    public function test_desativar_segundo_fator_exige_codigo_recente_sem_guardar_a_senha(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user = $this->criarUsuario('desativar-recente@example.invalid');
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user);
        $this->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at($secret, now()->getTimestamp())])
            ->assertRedirect('/');

        $this->travel(16)->minutes();
        $this->get('/seguranca/segundo-fator')->assertOk();
        $this->post('/seguranca/segundo-fator/desativar', ['password' => 'SenhaForteDeTeste123!'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull(session('astuti.two_factor.pending_input'));
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Para desativar o segundo fator');

        $this->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at($secret, now()->getTimestamp())])
            ->assertRedirect('/seguranca/segundo-fator');
        $this->post('/seguranca/segundo-fator/desativar', ['password' => 'SenhaForteDeTeste123!'])
            ->assertRedirect('/seguranca/segundo-fator');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }
}
