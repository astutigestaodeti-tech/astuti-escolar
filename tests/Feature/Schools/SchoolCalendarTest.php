<?php

namespace Tests\Feature\Schools;

use App\Calendar\SchoolCalendar;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolCalendarTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yearId = $this->inMunicipality(fn (): string => SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => 2027,
            'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
        ])->id);
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function userWithRole(string $role, array $schools = []): User
    {
        $user = $this->criarUsuario(Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function verify(User $user): void
    {
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(string $suffix = '', string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/calendario'.$suffix;
    }

    private function data(array $extra = []): array
    {
        return array_replace([
            'school_year_id' => $this->yearId,
            'date' => '2027-05-03',
            'kind' => 'holiday',
            'description' => 'Feriado fictício',
            'scope' => 'school',
        ], $extra);
    }

    private function entry(string $date, string $kind, ?string $school = self::ESCOLA_A1, ?string $year = null): SchoolCalendarDay
    {
        return SchoolCalendarDay::query()->create([
            'id' => (string) Str::uuid(),
            'school_year_id' => $year ?? $this->yearId,
            'school_id' => $school,
            'date' => $date,
            'kind' => $kind,
            'description' => 'Data de teste',
        ]);
    }

    public function test_contagem_desconta_feriado_e_recesso_e_soma_sabado_extra_sem_contar_eventos(): void
    {
        $this->inMunicipality(function (): void {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2028,
                'start_date' => '2028-03-01', 'end_date' => '2028-03-07', 'status' => 'planned',
            ]);
            $this->entry('2028-03-01', 'holiday', null, $year->id);
            $this->entry('2028-03-03', 'recess', self::ESCOLA_A1, $year->id);
            $this->entry('2028-03-04', 'extra_school_day', self::ESCOLA_A1, $year->id);
            $this->entry('2028-03-02', 'event', self::ESCOLA_A1, $year->id);
            $calendar = app(SchoolCalendar::class);
            $this->assertSame(4, $calendar->plannedDays($year, $calendar->entries($year->id, self::ESCOLA_A1)));
            $this->assertSame(4, $calendar->plannedDays($year, $calendar->entries($year->id, self::ESCOLA_A2)));

            // Reposição não pode transformar um feriado da rede em dia de aula.
            $this->entry('2028-03-01', 'extra_school_day', self::ESCOLA_A1, $year->id);
            $this->assertSame(4, $calendar->plannedDays($year, $calendar->entries($year->id, self::ESCOLA_A1)));
        });
    }

    public function test_gestao_cria_data_municipal_visivel_nas_escolas_e_audita(): void
    {
        $this->verify($this->userWithRole('municipal_manager'));
        $this->post($this->url(), $this->data(['scope' => 'municipality']))
            ->assertRedirect()->assertSessionHas('status', 'Dia incluído no calendário escolar.');

        $this->inMunicipality(function (): void {
            $entry = SchoolCalendarDay::query()->sole();
            $this->assertNull($entry->school_id);
            $created = AuditLog::query()->where('auditable_type', SchoolCalendarDay::class)->where('action', 'created')->sole();
            $this->assertSame('holiday', $created->after['kind']);
            $this->assertArrayNotHasKey('description', $created->after);
            $this->assertNull($created->before);
        });

        $this->verify($this->userWithRole('teacher', [self::ESCOLA_A2]));
        $this->get($this->url('?ano='.$this->yearId, self::ESCOLA_A2))
            ->assertOk()->assertSee('Feriado fictício')->assertSee('Toda a rede')
            ->assertDontSee('Adicionar data</h2>', false);
    }

    public function test_escola_altera_so_seu_calendario_e_nao_pode_criar_ou_apagar_data_municipal(): void
    {
        $this->verify($this->userWithRole('school_secretary', [self::ESCOLA_A1]));
        $this->get($this->url())->assertOk()->assertSee('Adicionar data');
        $this->post($this->url(), $this->data())->assertRedirect();

        $schoolEntry = $this->inMunicipality(fn (): SchoolCalendarDay => SchoolCalendarDay::query()->sole());
        $this->assertSame(self::ESCOLA_A1, $schoolEntry->school_id);
        $this->get($this->url('?ano='.$this->yearId, self::ESCOLA_A2))->assertForbidden();

        $this->post($this->url(), $this->data([
            'date' => '2027-05-04', 'scope' => 'municipality',
        ]))->assertForbidden();

        $network = $this->inMunicipality(fn (): SchoolCalendarDay => $this->entry('2027-05-05', 'recess', null));
        $this->delete($this->url('/'.$network->id))->assertForbidden();

        $this->delete($this->url('/'.$schoolEntry->id))->assertRedirect();
        $this->inMunicipality(function () use ($schoolEntry, $network): void {
            $this->assertDatabaseMissing('school_calendar_days', ['id' => $schoolEntry->id], 'tenant');
            $this->assertDatabaseHas('school_calendar_days', ['id' => $network->id], 'tenant');
            $deleted = AuditLog::query()->where('auditable_type', SchoolCalendarDay::class)->where('action', 'deleted')->sole();
            $this->assertSame($schoolEntry->id, $deleted->before['id']);
            $this->assertSame('holiday', $deleted->before['kind']);
            $this->assertNull($deleted->after);
        });
    }

    public function test_professor_nao_grava_e_outra_escola_nao_consulta(): void
    {
        $this->verify($this->userWithRole('teacher', [self::ESCOLA_A1]));
        $this->get($this->url())->assertOk();
        $this->post($this->url(), $this->data())->assertForbidden();
        $this->delete($this->url('/'.Str::uuid()))->assertForbidden();

        $this->verify($this->userWithRole('school_director', [self::ESCOLA_A2]));
        $this->get($this->url())->assertForbidden();
        $this->post($this->url(), $this->data())->assertForbidden();

        $this->assertSame(0, $this->inMunicipality(fn (): int => SchoolCalendarDay::query()->count()));
    }

    public function test_segundo_fator_e_validacoes_evitam_gravacao_invalida(): void
    {
        $user = $this->userWithRole('school_director', [self::ESCOLA_A1]);
        $this->actingAs($user)->post($this->url(), $this->data())
            ->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(0, $this->inMunicipality(fn (): int => SchoolCalendarDay::query()->count()));
        $this->verify($user);

        $this->post($this->url(), $this->data(['date' => '2028-01-01']))
            ->assertSessionHasErrors(['date' => 'A data deve estar entre o início e o fim do ano letivo escolhido.']);
        $this->post($this->url(), $this->data(['kind' => 'sem_permissao']))
            ->assertSessionHasErrors('kind');
        $this->post($this->url(), $this->data(['description' => '']))
            ->assertSessionHasErrors('description');
        $this->post($this->url(), $this->data())->assertRedirect();
        $this->post($this->url(), $this->data())->assertSessionHasErrors('date');
        $this->assertSame(1, $this->inMunicipality(fn (): int => SchoolCalendarDay::query()->count()));
    }

    public function test_apenas_gestor_municipal_remove_data_da_rede_com_segundo_fator(): void
    {
        $entry = $this->inMunicipality(fn (): SchoolCalendarDay => $this->entry('2027-08-12', 'holiday', null));
        $manager = $this->userWithRole('municipal_manager');

        $this->actingAs($manager)->delete($this->url('/'.$entry->id))
            ->assertRedirect('/seguranca/segundo-fator/desafio');
        $this->assertSame(1, $this->inMunicipality(fn (): int => SchoolCalendarDay::query()->count()));

        $this->verify($manager);
        $this->delete($this->url('/'.$entry->id))->assertRedirect();
        $this->inMunicipality(function () use ($entry): void {
            $this->assertSame(0, SchoolCalendarDay::query()->count());
            $log = AuditLog::query()->where('auditable_type', SchoolCalendarDay::class)->where('action', 'deleted')->sole();
            $this->assertSame($entry->id, $log->before['id']);
            $this->assertArrayNotHasKey('description', $log->before);
        });
    }

    public function test_indices_unicos_cobrem_data_igual_da_rede_e_de_uma_escola(): void
    {
        $this->inMunicipality(function (): void {
            $this->entry('2027-06-08', 'holiday', null);
            $this->entry('2027-06-08', 'event', self::ESCOLA_A1);

            foreach ([null, self::ESCOLA_A1] as $scope) {
                try {
                    $this->entry('2027-06-08', 'recess', $scope);
                    $this->fail('O banco deveria recusar datas duplicadas na abrangência.');
                } catch (QueryException $error) {
                    $this->assertSame('23505', $error->errorInfo[0]);
                }
            }
            $this->entry('2027-06-08', 'holiday', self::ESCOLA_A2);
            $this->assertSame(3, SchoolCalendarDay::query()->count());
        });
    }

    public function test_chamada_exibe_aviso_de_feriado_sem_bloquear_o_formulario(): void
    {
        $classId = $this->inMunicipality(function (): string {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => (int) today()->year,
                'start_date' => today()->startOfYear()->toDateString(),
                'end_date' => today()->endOfYear()->toDateString(),
                'status' => 'planned',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => '3º Ano Fictício', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                'shift' => 'Manhã', 'status' => 'active',
            ]);
            $this->entry(today()->toDateString(), 'holiday', null, $year->id);

            return $class->id;
        });
        $this->verify($this->userWithRole('school_director', [self::ESCOLA_A1]));
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.$classId.'/chamada')
            ->assertOk()->assertSee('não é dia letivo pelo calendário')
            ->assertSee('A chamada continua disponível para registro.');
    }
}
