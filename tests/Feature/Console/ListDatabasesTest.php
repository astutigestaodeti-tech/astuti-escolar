<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ListDatabasesTest extends TestCase
{
    public function test_lista_so_os_bancos_locais_sem_usuario_senha_ou_endereco(): void
    {
        config([
            'database.connections.platform' => [
                'driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'astuti_platform_x',
                'username' => 'conta_ficticia', 'password' => 'senha-ficticia',
            ],
            'tenancy.connections' => [
                'monte_sereno' => ['driver' => 'pgsql', 'host' => 'localhost', 'database' => 'astuti_monte_sereno_x', 'username' => 'u', 'password' => 'p'],
                'remota' => ['driver' => 'pgsql', 'host' => 'banco.exemplo.invalid', 'database' => 'astuti_remota', 'username' => 'u', 'password' => 'p'],
                'injecao' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'x; rm -rf /', 'username' => 'u', 'password' => 'p'],
            ],
        ]);

        $this->assertSame(0, Artisan::call('astuti:bancos'));
        $saida = Artisan::output();

        $this->assertSame(
            "plataforma astuti_platform_x\nprefeitura-monte-sereno astuti_monte_sereno_x\n",
            $saida,
        );
        foreach (['conta_ficticia', 'senha-ficticia', '127.0.0.1', 'exemplo.invalid', 'rm -rf'] as $segredo) {
            $this->assertStringNotContainsString($segredo, $saida);
        }
    }

    public function test_falha_quando_nao_ha_banco_local(): void
    {
        config([
            'database.connections.platform' => ['driver' => 'pgsql', 'host' => 'banco.exemplo.invalid', 'database' => 'astuti_platform_x'],
            'tenancy.connections' => [],
        ]);

        $this->assertSame(1, Artisan::call('astuti:bancos'));
    }
}
