<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SchoolMutationArchitectureTest extends TestCase
{
    private const FORBIDDEN = [
        'silent' => '/->(?:saveQuietly|updateQuietly|deleteQuietly|restoreQuietly)\s*\(/',
        'without_events' => '/::withoutEvents\s*\(/',
        'to_base' => '/->toBase\s*\(/',
        'get_query' => '/->getQuery\s*\(/',
        'raw_model_connection' => '/->getConnection\s*\(\s*\)\s*->table\s*\(/',
        'raw_tenant_table' => '/DB::connection\s*\(\s*[\'\"]tenant[\'\"]\s*\)\s*->table\s*\(\s*[\'\"](?:schools|school_years|school_classes|students|student_enrollments)[\'\"]/',
        'raw_tenant_write' => '/DB::connection\s*\(\s*[\'\"]tenant[\'\"]\s*\)\s*->(?:update|insert|delete|statement|unprepared|affectingStatement)\s*\(/',
    ];

    /** Exceções internas mínimas: estes caminhos possuem guardas verificadas por testes. */
    private const EXCEPTIONS = [
        'Models/Tenant/Concerns/HasSchoolHistory.php' => ['raw_model_connection'],
        'Models/Tenant/SchoolRecordBuilder.php' => ['to_base'],
    ];

    public function test_c2_codigo_da_aplicacao_nao_utiliza_atalhos_de_escrita_sem_auditoria(): void
    {
        $violations = [];
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(app_path().'/', '', $file->getPathname());
            $violations = array_merge($violations, $this->violations($relative, File::get($file->getPathname())));
        }

        $this->assertSame([], array_values(array_unique($violations)), 'Escrita escolar sem auditoria detectada em app/.');
    }

    public function test_n2_sondagens_de_atalhos_sao_bloqueadas_pelo_verificador(): void
    {
        foreach ([
            "SchoolClass::query()->toBase()->update(['name' => 'forjado']);",
            "SchoolClass::query()->getQuery()->update(['name' => 'forjado']);",
            "\$model->getConnection()->table('school_classes')->update(['name' => 'forjado']);",
            "DB::connection('tenant')->update('UPDATE school_classes SET name = ?');",
            "DB::connection('tenant')->insert('INSERT INTO students ...');",
            "DB::connection('tenant')->delete('DELETE FROM students');",
            "DB::connection('tenant')->statement('UPDATE students SET full_name = ?');",
            "DB::connection('tenant')->unprepared('UPDATE students ...');",
            "DB::connection('tenant')->affectingStatement('UPDATE students ...');",
        ] as $snippet) {
            $this->assertNotSame([], $this->violations('Imports/FutureImporter.php', $snippet), $snippet);
        }

        $this->assertSame([], $this->violations('Imports/FutureImporter.php', 'Student::query()->create($data);'));
    }

    /** @return list<string> */
    private function violations(string $relative, string $code): array
    {
        $violations = [];

        foreach (self::FORBIDDEN as $key => $pattern) {
            if (in_array($key, self::EXCEPTIONS[$relative] ?? [], true)) {
                continue;
            }

            if (preg_match($pattern, $code) === 1) {
                $violations[] = $relative.' ('.$key.')';
            }
        }

        return $violations;
    }
}
