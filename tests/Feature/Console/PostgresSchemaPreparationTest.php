<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PostgresSchemaPreparationTest extends TestCase
{
    public function test_nao_prepara_estrutura_fora_do_ambiente_local_ficticio(): void
    {
        $this->assertSame(1, Artisan::call('astuti:preparar-pg-vazio'));
        $this->assertStringContainsString('exclusivo', Artisan::output());
    }

    public function test_a_migracao_de_dados_nao_roda_no_ambiente_de_testes(): void
    {
        $this->assertSame(1, Artisan::call('astuti:migrar-dados-postgresql'));
        $this->assertStringContainsString('exclusivo', Artisan::output());
    }
}
