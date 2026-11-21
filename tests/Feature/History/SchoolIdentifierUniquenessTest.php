<?php

namespace Tests\Feature\History;

use App\Models\Tenant\School;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolIdentifierUniquenessTest extends TwoMunicipalityTestCase
{
    public function test_i4_ano_letivo_nao_reutiliza_identificador_historico_apos_exclusao_logica(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-17',
                'status' => 'planned',
            ]);
            $year->deleteWithReason('Registro fictício de teste', $this->auditFixtureUser->id);

            try {
                SchoolYear::query()->create([
                    'id' => (string) Str::uuid(),
                    'year' => 2027,
                    'start_date' => '2027-02-01',
                    'end_date' => '2027-12-17',
                    'status' => 'planned',
                ]);
                $this->fail('Ano letivo encerrado não pode ser duplicado no histórico.');
            } catch (QueryException) {
                $this->assertTrue(SchoolYear::withTrashed()->findOrFail($year->id)->trashed());
            }
        });
    }

    public function test_i4_codigo_inep_de_escola_excluida_logicamente_nao_e_reutilizado(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            $school->update(['inep_code' => '12345678']);
            $school->deleteWithReason('Cadastro fictício lançado por engano', $this->auditFixtureUser->id);

            try {
                School::query()->create([
                    'id' => (string) Str::uuid(),
                    'name' => 'Escola fictícia duplicada',
                    'inep_code' => '12345678',
                    'status' => 'active',
                ]);
                $this->fail('Código INEP já usado não pode ser reaproveitado.');
            } catch (QueryException) {
                $this->assertTrue(School::withTrashed()->findOrFail($school->id)->trashed());
            }
        });
    }
}
