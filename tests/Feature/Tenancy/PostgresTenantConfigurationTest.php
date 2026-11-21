<?php

namespace Tests\Feature\Tenancy;

use App\Models\Platform\Municipality;
use App\Tenancy\TenantConnectionManager;
use InvalidArgumentException;
use Tests\TestCase;

class PostgresTenantConfigurationTest extends TestCase
{
    public function test_o_banco_central_usa_postgresql_na_porta_padrao(): void
    {
        $platform = config('database.connections.platform');

        $this->assertSame('pgsql', $platform['driver']);
        $this->assertSame('5432', (string) $platform['port']);
    }

    public function test_a_conexao_municipal_recusa_mariadb(): void
    {
        config()->set('tenancy.connections', [
            'monte_sereno' => [
                'driver' => 'mariadb',
                'host' => 'localhost',
                'port' => '3307',
                'database' => 'astuti_monte_sereno_dev',
                'username' => 'usuario_ficticio',
                'password' => '',
                'url' => null,
            ],
        ]);

        $municipio = new Municipality(['db_key' => 'monte_sereno']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conexao municipal nao configurada.');

        app(TenantConnectionManager::class)->activate($municipio);
    }
}
