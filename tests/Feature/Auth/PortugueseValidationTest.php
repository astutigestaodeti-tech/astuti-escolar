<?php

namespace Tests\Feature\Auth;

use Tests\Support\TwoMunicipalityTestCase;

class PortugueseValidationTest extends TwoMunicipalityTestCase
{
    public function test_email_invalido_no_login_exibe_mensagem_e_nome_do_campo_em_portugues(): void
    {
        app()->setLocale('pt_BR');

        $this->post('/login', ['email' => 'nao-e-email', 'password' => 'Teste123!'])
            ->assertSessionHasErrors(['email' => 'O campo e-mail deve ser um endereço de e-mail válido.']);
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
