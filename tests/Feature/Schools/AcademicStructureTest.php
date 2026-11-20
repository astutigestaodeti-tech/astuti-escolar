<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Tests\Support\TwoMunicipalityTestCase;

class AcademicStructureTest extends TwoMunicipalityTestCase
{
    public function test_authorized_school_classes_show_their_academic_year(): void
    {
        $user = $this->criarUsuario('gestor-turmas@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');

        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create([
                'id' => '44444444-4444-4444-8444-444444444444',
                'year' => 2026,
                'start_date' => '2026-02-02',
                'end_date' => '2026-12-18',
                'status' => 'active',
            ]);

            SchoolClass::query()->create([
                'id' => '55555555-5555-4555-8555-555555555555',
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year->id,
                'name' => '5º ano A',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Manhã',
                'capacity' => 30,
                'status' => 'active',
            ]);
        });

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas')
            ->assertOk()
            ->assertSee('Unidade A')
            ->assertSee('5º ano A')
            ->assertSee('2026')
            ->assertSee('Manhã')
            ->assertSee('30');

        $this->get('/m/municipio-b/escolas/'.self::ESCOLA_B1.'/turmas')->assertForbidden();
    }

    public function test_secretary_cannot_open_classes_from_another_school(): void
    {
        $user = $this->criarUsuario('secretaria-turmas@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/turmas')
            ->assertForbidden();
    }

    public function test_class_cannot_point_to_nonexistent_school_within_municipality(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = SchoolYear::query()->create([
                'id' => '66666666-6666-4666-8666-666666666666',
                'year' => 2027,
                'start_date' => '2027-02-01',
                'end_date' => '2027-12-17',
                'status' => 'planned',
            ]);

            $this->expectException(QueryException::class);

            SchoolClass::query()->create([
                'id' => '77777777-7777-4777-8777-777777777777',
                'school_id' => '99999999-9999-4999-8999-999999999999',
                'school_year_id' => $year->id,
                'name' => 'Turma inexistente',
                'stage' => 'Ensino Fundamental',
                'modality' => 'Regular',
                'shift' => 'Tarde',
                'status' => 'planned',
            ]);
        });
    }
}
