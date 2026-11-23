<?php

namespace Tests\Feature\Schools;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolCalendarDay;
use App\Models\Tenant\SchoolYear;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class CalendarNationalHolidaysTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = $this->tenant(fn (): string => SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active'])->id);
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function login(string $role, array $schools = [self::ESCOLA_A1]): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])->assertRedirect();
    }

    private function url(string $suffix = ''): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/calendario'.$suffix;
    }

    public function test_direcao_inclui_os_feriados_do_ano_letivo_so_para_a_escola_sem_duplicar(): void
    {
        $this->login('school_director');

        $this->post($this->url('/feriados-nacionais'), ['school_year_id' => $this->year])
            ->assertSessionHas('status', '11 feriados nacionais incluídos para esta escola. Confira os feriados estaduais e municipais.');

        $entries = $this->tenant(fn () => SchoolCalendarDay::query()->orderBy('date')->get());
        $this->assertCount(11, $entries);
        $this->assertSame([self::ESCOLA_A1], $entries->pluck('school_id')->unique()->values()->all());
        $this->assertSame('2027-02-08', $entries->first()->date->toDateString());
        $this->assertFalse($entries->contains(fn ($day) => $day->date->toDateString() === '2027-12-25'));
        $this->assertSame(11, $this->tenant(fn (): int => AuditLog::query()->where('auditable_type', SchoolCalendarDay::class)->where('action', 'created')->count()));

        $this->post($this->url('/feriados-nacionais'), ['school_year_id' => $this->year])
            ->assertSessionHas('status', 'Os feriados nacionais deste ano letivo já estavam no calendário.');
        $this->assertSame(11, $this->tenant(fn (): int => SchoolCalendarDay::query()->count()));

        $this->get($this->url('?ano='.$this->year.'&mes=2027-02'))->assertOk()
            ->assertSee('Carnaval (ponto facultativo)')->assertSee('Incluir feriados nacionais');
    }

    public function test_gestao_municipal_grava_para_a_rede_e_pula_datas_ja_lancadas(): void
    {
        $this->tenant(fn () => SchoolCalendarDay::query()->create(['id' => (string) Str::uuid(), 'school_year_id' => $this->year, 'school_id' => self::ESCOLA_A1, 'date' => '2027-04-21', 'kind' => 'holiday', 'description' => 'Tiradentes']));
        $this->login('municipal_manager', []);

        $this->post($this->url('/feriados-nacionais'), ['school_year_id' => $this->year])
            ->assertSessionHas('status', '10 feriados nacionais incluídos para toda a rede (1 já estava no calendário). Confira os feriados estaduais e municipais.');
        $this->assertSame(10, $this->tenant(fn (): int => SchoolCalendarDay::query()->whereNull('school_id')->count()));
    }

    public function test_professor_nao_inclui_feriados(): void
    {
        $this->login('teacher');
        $this->post($this->url('/feriados-nacionais'), ['school_year_id' => $this->year])->assertForbidden();
        $this->get($this->url())->assertOk()->assertDontSee('Incluir feriados nacionais');
        $this->assertSame(0, $this->tenant(fn (): int => SchoolCalendarDay::query()->count()));
    }
}
