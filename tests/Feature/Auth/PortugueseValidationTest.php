<?php

namespace Tests\Feature\Auth;

use Tests\Support\TwoMunicipalityTestCase;

class PortugueseValidationTest extends TwoMunicipalityTestCase
{
    public function test_login_invalido_exibe_mensagem_em_portugues_sem_revelar_o_motivo(): void
    {
        app()->setLocale('pt_BR');

        // O campo aceita e-mail ou usuário curto; qualquer erro mostra a mesma mensagem.
        foreach (['nao-e-email', 'com espaço'] as $login) {
            $this->post('/login', ['email' => $login, 'password' => 'Teste123!'])
                ->assertSessionHasErrors(['email' => 'As credenciais informadas não correspondem aos nossos registros.']);
        }
    }

    public function test_formulario_sem_campos_exibe_erros_em_portugues(): void
    {
        app()->setLocale('pt_BR');

        $this->post('/login', [])->assertSessionHasErrors([
            'email' => 'O campo e-mail é obrigatório.',
            'password' => 'O campo senha é obrigatório.',
        ]);
    }

    public function test_mensagens_padrao_de_autenticacao_e_recuperacao_sao_em_portugues(): void
    {
        app()->setLocale('pt_BR');
        $this->assertStringContainsString('credenciais', __('auth.failed'));
        $this->assertStringContainsString('senha', __('passwords.reset'));
        $this->assertStringContainsString('Próximo', __('pagination.next'));
    }
}
