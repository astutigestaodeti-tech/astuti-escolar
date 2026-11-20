<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SchoolMutationArchitectureTest extends TestCase
{
    public function test_c2_codigo_da_aplicacao_nao_utiliza_atalhos_de_escrita_sem_auditoria(): void
    {
        $forbidden = [
            '/->(?:saveQuietly|updateQuietly|deleteQuietly|restoreQuietly)\s*\(/',
            '/::withoutEvents\s*\(/',
            '/DB::connection\s*\(\s*[\'\"]tenant[\'\"]\s*\)\s*->table\s*\(\s*[\'\"](?:schools|school_years|school_classes|students|student_enrollments)[\'\"]/',
        ];

        $violations = [];
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = File::get($file->getPathname());
            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $code)) {
                    $violations[] = str_replace(app_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], array_values(array_unique($violations)), 'Escrita escolar sem auditoria detectada em app/.');
    }
}
