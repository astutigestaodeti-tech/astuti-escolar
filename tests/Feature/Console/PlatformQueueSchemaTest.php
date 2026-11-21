<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Schema;
use Tests\Support\TwoMunicipalityTestCase;

class PlatformQueueSchemaTest extends TwoMunicipalityTestCase
{
    public function test_o_banco_central_tem_todas_as_tabelas_necessarias_as_filas(): void
    {
        foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(
                Schema::connection('platform')->hasTable($table),
                "A tabela operacional {$table} precisa existir no banco central.",
            );
        }
    }
}
