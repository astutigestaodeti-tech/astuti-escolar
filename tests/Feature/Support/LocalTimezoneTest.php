<?php

namespace Tests\Feature\Support;

use App\Tenancy\ConfiguredTenantConnections;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocalTimezoneTest extends TestCase
{
    public function test_aplicacao_e_banco_usam_o_horario_de_brasilia(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('America/Sao_Paulo', config('database.connections.platform.timezone'));
        $this->assertSame('America/Sao_Paulo', DB::connection('platform')->selectOne('SHOW timezone')->TimeZone);

        // Às 21h30 de Brasília já é outro dia em UTC; "hoje" precisa ser o dia local.
        $this->travelTo(now()->setDate(2026, 11, 22)->setTime(21, 30));
        $this->assertSame('2026-11-22', today()->toDateString());
    }

    public function test_conexao_do_municipio_tambem_recebe_o_fuso(): void
    {
        $connections = ConfiguredTenantConnections::forOneMunicipality([
            'db_key' => 'tenant_monte_sereno', 'host' => '127.0.0.1', 'port' => '5432',
            'database' => 'tenant_ficticio', 'username' => 'usuario_ficticio', 'password' => 'senha-ficticia',
        ]);

        $this->assertSame('America/Sao_Paulo', collect($connections)->first()['timezone'] ?? null);
    }
}
