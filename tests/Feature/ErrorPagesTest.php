<?php

namespace Tests\Feature;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_paginas_de_erro_explicam_em_portugues(): void
    {
        $casos = [
            403 => 'Acesso não permitido',
            404 => 'Página não encontrada',
            405 => 'Esta página não abre pelo endereço',
            419 => 'A página expirou',
            429 => 'Muitas tentativas seguidas',
            500 => 'Algo deu errado',
            503 => 'Sistema em manutenção',
        ];

        foreach ($casos as $status => $titulo) {
            $arquivo = view()->exists('errors.'.$status) ? 'errors.'.$status : ($status >= 500 ? 'errors.5xx' : 'errors.4xx');
            $html = view($arquivo, ['exception' => new HttpException($status)])->render();
            $this->assertStringContainsString($titulo, $html, 'Codigo '.$status);
            $this->assertStringContainsString('lang="pt-BR"', $html);
            $this->assertStringContainsString('Ir para o início', $html);
        }
    }

    public function test_mensagem_propria_do_403_aparece_e_a_generica_em_ingles_nao(): void
    {
        $html = view('errors.4xx', ['exception' => new HttpException(403, 'Confirme o segundo fator de autenticação antes de alterar registros escolares.')])->render();
        $this->assertStringContainsString('Confirme o segundo fator', $html);

        $html = view('errors.4xx', ['exception' => new HttpException(403, 'This action is unauthorized.')])->render();
        $this->assertStringNotContainsString('This action is unauthorized.', $html);
    }

    public function test_endereco_inexistente_mostra_pagina_em_portugues(): void
    {
        $this->get('/pagina-que-nao-existe')->assertNotFound()->assertSee('Página não encontrada');
    }
}
