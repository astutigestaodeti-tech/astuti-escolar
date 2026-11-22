<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Schema;
use Tests\Support\TwoMunicipalityTestCase;

class PlatformQueueSchemaTest extends TwoMunicipalityTestCase
{
    public function test_o_banco_central_tem_todas_as_tabelas_necessarias_as_filas(): void
    {
        // A CI pode ter criado as tabelas por outras migrations; o instalador
        // separado da plataforma precisa conseguir cria-las sozinho.
        $this->assertFileExists(database_path('migrations/platform/0001_01_01_000002_create_jobs_table.php'));

        foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(
                Schema::connection('platform')->hasTable($table),
                "A tabela operacional {$table} precisa existir no banco central.",
            );
        }
    }
}
