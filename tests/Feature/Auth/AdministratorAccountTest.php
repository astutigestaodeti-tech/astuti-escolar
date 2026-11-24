<?php

namespace Tests\Feature\Auth;

use App\Models\Platform\Membership;
use App\Models\Platform\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TwoMunicipalityTestCase;

class AdministratorAccountTest extends TwoMunicipalityTestCase
{
    public function test_comando_cria_admin_com_a_senha_digitada_e_nao_troca_na_proxima_execucao(): void
    {
        $this->artisan('astuti:usuario-admin')
            ->expectsQuestion('Digite a senha (não aparece na tela; mínimo de 8 caracteres)', 'senha-ficticia-1')
            ->expectsQuestion('Digite a senha de novo', 'senha-ficticia-1')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@usuarios.astuti.invalid')->firstOrFail();
        $this->assertTrue(Hash::check('senha-ficticia-1', $user->password));
        $networks = Municipality::query()->where('status', 'active')->count();
        $this->assertSame($networks, Membership::query()->where('user_id', $user->id)->where('role', 'municipal_manager')->count());

        // Rodar de novo (por exemplo, numa atualização) mantém a senha.
        $this->artisan('astuti:usuario-admin')->assertSuccessful();
        $this->assertTrue(Hash::check('senha-ficticia-1', $user->fresh()->password));
        $this->assertSame($networks, Membership::query()->where('user_id', $user->id)->count());

        // Senhas diferentes ou curtas não alteram nada.
        $this->artisan('astuti:usuario-admin', ['--trocar-senha' => true])
            ->expectsQuestion('Digite a senha (não aparece na tela; mínimo de 8 caracteres)', 'curta')
            ->expectsQuestion('Digite a senha (não aparece na tela; mínimo de 8 caracteres)', 'outra-senha-1')
            ->expectsQuestion('Digite a senha de novo', 'diferente-1')
            ->expectsQuestion('Digite a senha (não aparece na tela; mínimo de 8 caracteres)', 'outra-senha-1')
            ->expectsQuestion('Digite a senha de novo', 'diferente-2')
            ->assertFailed();
        $this->assertTrue(Hash::check('senha-ficticia-1', $user->fresh()->password));
    }

    public function test_login_aceita_o_usuario_curto_e_o_email_completo(): void
    {
        $this->artisan('astuti:usuario-admin')
            ->expectsQuestion('Digite a senha (não aparece na tela; mínimo de 8 caracteres)', 'senha-ficticia-1')
            ->expectsQuestion('Digite a senha de novo', 'senha-ficticia-1')
            ->assertSuccessful();

        $this->get('/login')->assertOk()->assertSee('E-mail ou usuário');
        $this->post('/login', ['email' => 'erro de digitação', 'password' => 'x'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'admin', 'password' => 'errada-123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => ' Admin ', 'password' => 'senha-ficticia-1'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
