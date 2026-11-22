<?php

namespace Tests\Feature\Import;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class PreviewStudentImportCommandTest extends TwoMunicipalityTestCase
{
    private const PLANILHA_E02 = __DIR__.'/../../Fixtures/kit-monte-sereno/E02_EMEF_Sebastiao_alunos_2027.xlsx';

    private function turmasDoKitNaEscolaA1(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $ano = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-08',
                'end_date' => '2027-12-17',
                'status' => 'planned',
            ]);

            foreach (['1º Ano A' => 'Manhã', '2º Ano A' => 'Manhã', '3º Ano A' => 'Manhã', '4º Ano A' => 'Tarde', '5º Ano A' => 'Tarde'] as $nome => $turno) {
                SchoolClass::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $ano->id,
                    'name' => $nome,
                    'stage' => 'Ensino Fundamental',
                    'modality' => 'Regular',
                    'shift' => $turno,
                    'status' => 'planned',
                ]);
            }
        });
    }

    /** @return array{int, int, int} */
    private function contagens(): array
    {
        return app(TenantContext::class)->runFor($this->municipioA, fn (): array => [
            Student::query()->withTrashed()->count(),
            StudentEnrollment::query()->withTrashed()->count(),
            AuditLog::query()->count(),
        ]);
    }

    public function test_previa_da_planilha_da_emef_mostra_conferencias_e_nao_grava_nada(): void
    {
        $this->turmasDoKitNaEscolaA1();
        $antes = $this->contagens();

        $this->artisan('astuti:importar-alunos-previa', [
            'arquivo' => self::PLANILHA_E02,
            '--escola' => self::ESCOLA_A1,
            '--municipio' => 'municipio-a',
            '--referencia' => '2027-07-30',
        ])
            ->expectsOutputToContain('Linha de total')
            ->expectsOutputToContain('Total: 58 alunos | prontos: 0 | conferir: 2 | com pendencias: 56 | linhas ignoradas: 1')
            ->expectsOutputToContain('Nenhum dado foi gravado')
            ->assertSuccessful();

        $this->assertSame($antes, $this->contagens());
        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_escola_e_obrigatoria_e_nunca_deduzida_do_arquivo(): void
    {
        $this->artisan('astuti:importar-alunos-previa', ['arquivo' => self::PLANILHA_E02, '--municipio' => 'municipio-a'])
            ->expectsOutputToContain('Informe a escola de destino')
            ->assertFailed();
    }

    public function test_escola_de_outro_municipio_nao_e_encontrada(): void
    {
        $this->turmasDoKitNaEscolaA1();

        $this->artisan('astuti:importar-alunos-previa', [
            'arquivo' => self::PLANILHA_E02,
            '--escola' => self::ESCOLA_B1,
            '--municipio' => 'municipio-a',
        ])
            ->expectsOutputToContain('Escola nao encontrada')
            ->assertFailed();
    }

    public function test_escola_sem_turmas_no_ano_pede_cadastro_das_turmas_antes(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, fn () => SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-08', 'end_date' => '2027-12-17', 'status' => 'planned',
        ]));

        $this->artisan('astuti:importar-alunos-previa', [
            'arquivo' => self::PLANILHA_E02,
            '--escola' => self::ESCOLA_A2,
            '--municipio' => 'municipio-a',
        ])
            ->expectsOutputToContain('Cadastre as turmas antes de importar')
            ->assertFailed();
    }

    public function test_arquivo_que_nao_e_planilha_e_recusado(): void
    {
        $this->turmasDoKitNaEscolaA1();
        $falso = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($falso, 'nao sou xlsx');

        try {
            $this->artisan('astuti:importar-alunos-previa', [
                'arquivo' => $falso,
                '--escola' => self::ESCOLA_A1,
                '--municipio' => 'municipio-a',
            ])
                ->expectsOutputToContain('Envie um PDF ou uma foto em JPG ou PNG.')
                ->assertFailed();
        } finally {
            @unlink($falso);
        }
    }
}
