<?php

namespace Tests\Feature\Auth;

use Tests\Support\TwoMunicipalityTestCase;

class LoginSessionTest extends TwoMunicipalityTestCase
{
    public function test_login_valido_abre_sessao_e_logout_encerra(): void
    {
        $user = $this->criarUsuario('acesso@example.invalid');

        $this->get('/login')->assertOk()->assertSee('Acessar');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'SenhaForteDeTeste123!',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_senha_incorreta_nao_abre_sessao(): void
    {
        $this->criarUsuario('senhaincorreta@example.invalid');

        $this->post('/login', [
            'email' => 'senhaincorreta@example.invalid',
            'password' => 'senha-incorreta',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_usuario_inativo_nao_consegue_entrar(): void
    {
        $this->criarUsuario('inativo@example.invalid', 'inactive');

        $this->post('/login', [
            'email' => 'inativo@example.invalid',
            'password' => 'SenhaForteDeTeste123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_cadastro_publico_nao_esta_disponivel(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_tentativas_repetidas_de_login_sao_limitadas(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => 'limitado@example.invalid',
                'password' => 'errada',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'limitado@example.invalid',
            'password' => 'errada',
        ])->assertStatus(429);
    }
}
