<?php

namespace Tests\Feature\Demo;

use Tests\TestCase;

class LocalDemoConfigurationTest extends TestCase
{
    public function test_configura_monte_sereno_somente_quando_banco_central_e_local_reconhecido(): void
    {
        $this->app['env'] = 'local';
        config()->set('database.connections.platform', $this->centralLocal());

        $settings = (require config_path('tenancy.php'))['connections']['monte_sereno'] ?? null;

        $this->assertIsArray($settings);
        $this->assertSame('mariadb', $settings['driver']);
        $this->assertSame('astuti_monte_sereno_dev', $settings['database']);
        $this->assertSame('127.0.0.1', $settings['host']);
        $this->assertSame('3307', $settings['port']);
        $this->assertSame('usuario-local', $settings['username']);
        $this->assertSame('/run/mysqld/mysqld.sock', $settings['unix_socket']);
    }

    public function test_em_producao_a_configuracao_municipal_continua_vazia(): void
    {
        $this->app['env'] = 'production';
        config()->set('database.connections.platform', $this->centralLocal());

        $this->assertSame([], (require config_path('tenancy.php'))['connections']);
    }

    public function test_servidor_remoto_e_url_externa_nao_habilitam_conexao_ficticia(): void
    {
        $this->app['env'] = 'local';

        foreach ([['host' => 'db.example.org'], ['url' => 'mariadb://example.org/demo']] as $unsafe) {
            config()->set('database.connections.platform', array_replace($this->centralLocal(), $unsafe));
            $this->assertSame([], (require config_path('tenancy.php'))['connections']);
        }
    }

    public function test_banco_central_de_outro_projeto_nao_habilita_demo(): void
    {
        $this->app['env'] = 'local';
        config()->set('database.connections.platform', array_replace($this->centralLocal(), [
            'database' => 'escola_producao',
        ]));

        $this->assertSame([], (require config_path('tenancy.php'))['connections']);
    }

    private function centralLocal(): array
    {
        return [
            'driver' => 'mariadb',
            'host' => '127.0.0.1',
            'port' => '3307',
            'database' => 'astuti_platform_dev',
            'username' => 'usuario-local',
            'password' => '',
            'unix_socket' => '/run/mysqld/mysqld.sock',
            'url' => null,
        ];
    }
}
