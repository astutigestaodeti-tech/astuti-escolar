<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\RequireConfirmedTwoFactor;
use App\Models\Platform\PlatformAuditLog;
use App\Security\DevelopmentAdminAccess;
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

    public function test_excecao_admin_local_exige_login_auditado_ip_prazo_e_nao_desativa_totp(): void
    {
        $user = $this->criarUsuario('admin@usuarios.astuti.invalid');
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();
        Route::post('/_teste/escrita-recente', fn () => response('gravado'))
            ->middleware(['web', 'auth', RequireConfirmedTwoFactor::class.':recente']);
        app()->detectEnvironment(fn () => 'local');
        config()->set('app.env', 'local');
        config()->set('auth.development_admin', [
            'enabled' => true,
            'ip' => '8.8.4.4',
            'expires_at' => (string) now()->addHour()->getTimestamp(),
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4', 'HTTPS' => 'on']);
        try {
            $this->actingAs($user)->post('https://localhost/_teste/escrita-escolar')->assertForbidden();
            $this->post('https://localhost/logout')->assertRedirect('/login');
            $this->post('https://localhost/login', ['email' => 'admin', 'password' => 'SenhaForteDeTeste123!'])
                ->assertRedirect('/')->assertSessionHas(DevelopmentAdminAccess::SESSION_KEY)
                ->assertSessionMissing('astuti.two_factor');
            $this->assertTrue(PlatformAuditLog::query()->where('user_id', $user->id)->where('action', 'auth.login_dev_password_only')->exists());
            $this->post('https://localhost/_teste/escrita-escolar')->assertOk();
            $this->post('https://localhost/_teste/escrita-recente')->assertOk();
            $this->post('https://localhost/seguranca/segundo-fator/desativar', ['password' => 'SenhaForteDeTeste123!'])->assertForbidden();
            $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
            $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8', 'HTTPS' => 'on'])
                ->post('https://localhost/_teste/escrita-escolar')->assertForbidden();
            $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4', 'HTTPS' => 'on']);
            config()->set('auth.development_admin.enabled', false);
            $this->post('https://localhost/_teste/escrita-escolar')->assertForbidden();
            config()->set('auth.development_admin.enabled', true);
            app()->detectEnvironment(fn () => 'production');
            config()->set('app.env', 'production');
            $this->post('https://localhost/_teste/escrita-escolar')->assertForbidden();
            app()->detectEnvironment(fn () => 'local');
            config()->set('app.env', 'local');
            $this->travel(61)->minutes();
            $this->post('https://localhost/_teste/escrita-escolar')->assertForbidden();
        } finally {
            $this->travelBack();
            app()->detectEnvironment(fn () => 'testing');
            config()->set('app.env', 'testing');
        }
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

        // QR Code desenhado no servidor, sem servico externo, e a chave manual como alternativa.
        $this->get('/seguranca/segundo-fator')->assertOk()
            ->assertSee('<svg xmlns="http://www.w3.org/2000/svg"', false)
            ->assertSee('aria-label="QR Code para cadastrar no aplicativo autenticador"', false)
            ->assertSee($user->two_factor_pending_secret)
            ->assertSee('Apague só a conta Astuti antiga')
            ->assertDontSee('chart.googleapis', false)
            ->assertDontSee('api.qrserver', false);

        $code = app(Totp::class)->at($user->two_factor_pending_secret, now()->getTimestamp());
        $this->post('/seguranca/segundo-fator/confirmar', ['code' => $code])
            ->assertRedirect('/seguranca/segundo-fator');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->post('/_teste/escrita-escolar')->assertOk();

        // Depois de ativado, o QR Code e a chave nao aparecem mais.
        $this->get('/seguranca/segundo-fator')->assertOk()
            ->assertDontSee('QR Code para cadastrar', false)
            ->assertDontSee($code.'');
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

    public function test_voltar_da_seguranca_nao_aponta_para_endereco_de_envio(): void
    {
        $user = $this->criarUsuario('voltar-seguranca@example.invalid');
        $iniciar = 'http://localhost/seguranca/segundo-fator/iniciar';

        $this->actingAs($user)->withHeader('referer', $iniciar)
            ->get('/seguranca/segundo-fator')
            ->assertOk()
            ->assertSee('href="'.route('home').'"', false)
            ->assertDontSee('href="'.$iniciar.'"', false);
    }
}
