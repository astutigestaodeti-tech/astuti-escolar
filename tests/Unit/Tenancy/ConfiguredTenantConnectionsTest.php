<?php

namespace Tests\Unit\Tenancy;

use App\Tenancy\ConfiguredTenantConnections;
use PHPUnit\Framework\TestCase;

class ConfiguredTenantConnectionsTest extends TestCase
{
    public function test_producao_configura_somente_o_banco_do_municipio_desta_instalacao(): void
    {
        $connections = ConfiguredTenantConnections::forOneMunicipality($this->values());

        $this->assertSame(['monte_sereno'], array_keys($connections));
        $this->assertSame('pgsql', $connections['monte_sereno']['driver']);
        $this->assertSame('127.0.0.1', $connections['monte_sereno']['host']);
        $this->assertSame('5432', (string) $connections['monte_sereno']['port']);
        $this->assertSame('astuti_monte_sereno', $connections['monte_sereno']['database']);
        $this->assertNull($connections['monte_sereno']['url']);
    }

    public function test_rejeita_credenciais_incompletas_e_destinos_externos(): void
    {
        foreach ([
            ['password' => ''],
            ['db_key' => 'municipio/invalido'],
            ['database' => 'banco;drop table'],
            ['host' => 'db.outro-municipio.example'],
            ['port' => 'abc'],
            ['port' => '70000'],
        ] as $invalid) {
            $this->assertSame([], ConfiguredTenantConnections::forOneMunicipality(array_replace($this->values(), $invalid)));
        }
    }

    private function values(): array
    {
        return [
            'db_key' => 'monte_sereno',
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => 'astuti_monte_sereno',
            'username' => 'astuti_app',
            'password' => 'senha-apenas-ficticia',
        ];
    }
}
