<?php

namespace Tests\Feature\Waitlist;

use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\WaitlistRequest;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class WaitlistEnrollmentTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $classA1;

    private string $classA2;

    private string $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 15:00:00'));
        config()->set('waitlist.cutoff_date', '2027-03-31');

        [$this->classA1, $this->classA2, $this->request] = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027,
                'start_date' => '2027-02-01', 'end_date' => '2027-12-20', 'status' => 'planned',
            ]);
            $classes = [];
            foreach ([self::ESCOLA_A1 => 'Maternal I', self::ESCOLA_A2 => 'Maternal I'] as $school => $name) {
                SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => $school, 'school_year_id' => $year->id,
                    'name' => 'Pré II', 'stage' => 'Educação Infantil', 'modality' => 'Regular', 'shift' => 'Tarde', 'status' => 'active',
                ]);
                $classes[] = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => $school, 'school_year_id' => $year->id,
                    'name' => $name, 'stage' => 'Educação Infantil', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
                ])->id;
            }
            $record = WaitlistRequest::query()->create([
                'id' => (string) Str::uuid(), 'protocol' => 'MATRICULA2', 'child_name' => 'Bia Criança Fictícia',
                'birth_date' => '2024-10-01', 'cpf' => null, 'guardian_name' => 'Rosa Responsável Fictícia',
                'guardian_phone' => '11987654321', 'postal_code' => '01001-000', 'address_street' => 'Rua Fictícia',
                'address_number' => '10', 'address_district' => 'Bairro Teste', 'address_city' => 'Cidade Inventada',
                'address_state' => 'SP', 'intake_year' => 2027, 'cutoff_date' => '2027-03-31', 'stage' => 'maternal_1',
                'desired_shift' => 'manha', 'priority_criteria' => [], 'priority_count' => 0, 'status' => 'convocado',
                'requested_at' => now(), 'created_by' => $this->auditFixtureUser->id,
            ]);
            foreach ([self::ESCOLA_A1, self::ESCOLA_A2] as $index => $school) {
                $record->preferences()->create(['id' => (string) Str::uuid(), 'school_id' => $school, 'preference_order' => $index + 1]);
            }

            return [...$classes, $record->id];
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(): string
    {
        return '/m/municipio-a/lista-de-espera/'.$this->request.'/matricular';
    }

    public function test_secretaria_matricula_a_crianca_da_fila_sem_redigitar_e_encerra_o_pedido(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->get($this->url())->assertOk()
            ->assertSee('Maternal I - Educação Infantil - Manhã (sugerida)')
            ->assertSee('value="2027-02-01"', false)
            ->assertDontSee('Outra Unidade A');

        $this->post($this->url(), ['school_class_id' => $this->classA1, 'started_at' => '2027-02-01', 'relationship' => 'mother'])
            ->assertRedirect();

        $data = $this->inMunicipality(function (): array {
            $student = Student::query()->where('full_name', 'Bia Criança Fictícia')->sole();
            $enrollment = StudentEnrollment::query()->where('student_id', $student->id)->sole();
            $record = WaitlistRequest::query()->findOrFail($this->request);

            return [
                'birth' => $student->birth_date->format('Y-m-d'),
                'cep' => $student->postal_code,
                'class' => $enrollment->school_class_id,
                'enrollment' => $enrollment->id,
                'guardian' => StudentGuardian::query()->where('student_id', $student->id)->value('relationship'),
                'status' => $record->status,
                'link' => [$record->student_id, $record->enrollment_id],
                'student' => $student->id,
                'history' => DB::connection('tenant')->table('waitlist_status_changes')->where('waitlist_request_id', $this->request)->value('new_status'),
            ];
        });

        $this->assertSame('2024-10-01', $data['birth']);
        $this->assertSame('01001-000', $data['cep']);
        $this->assertSame($this->classA1, $data['class']);
        $this->assertSame('mother', $data['guardian']);
        $this->assertSame('atendido', $data['status']);
        $this->assertSame([$data['student'], $data['enrollment']], $data['link']);
        $this->assertSame('atendido', $data['history']);

        // Pedido encerrado não matricula de novo.
        $this->post($this->url(), ['school_class_id' => $this->classA1, 'started_at' => '2027-02-01', 'relationship' => 'mother'])
            ->assertSessionHasErrors();
        $this->assertSame(1, $this->inMunicipality(fn (): int => Student::query()->count()));
    }

    public function test_turma_de_escola_que_a_pessoa_nao_gere_e_recusada(): void
    {
        $this->login('school_secretary', [self::ESCOLA_A1]);

        $this->post($this->url(), ['school_class_id' => $this->classA2, 'started_at' => '2027-02-01', 'relationship' => 'other'])
            ->assertSessionHasErrors('school_class_id');
        $this->assertSame(0, $this->inMunicipality(fn (): int => Student::query()->count()));
    }

    public function test_crianca_ja_cadastrada_nao_gera_cadastro_repetido(): void
    {
        $this->inMunicipality(fn () => Student::query()->create([
            'id' => (string) Str::uuid(), 'full_name' => 'BIA CRIANCA FICTICIA', 'birth_date' => '2024-10-01',
        ]));
        $this->login('municipal_manager', []);

        $this->get($this->url())->assertOk()->assertSee('Esta criança já está no cadastro');
        $this->post($this->url(), ['school_class_id' => $this->classA1, 'started_at' => '2027-02-01', 'relationship' => 'other'])
            ->assertSessionHasErrors('matricula');
        $this->assertSame(1, $this->inMunicipality(fn (): int => Student::query()->count()));
    }

    public function test_professor_nao_matricula_e_data_fora_do_ano_e_recusada(): void
    {
        $this->login('teacher', [self::ESCOLA_A1]);
        $this->get($this->url())->assertForbidden();

        $this->login('municipal_manager', []);
        $this->post($this->url(), ['school_class_id' => $this->classA1, 'started_at' => '2026-12-01', 'relationship' => 'other'])
            ->assertSessionHasErrors('started_at');
    }
}
