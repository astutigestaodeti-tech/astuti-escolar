<?php

namespace Tests\Feature\Academics;

use App\Academics\LessonDiary;
use App\Academics\PedagogicalPanel;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\LessonRecord;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffMember;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class LessonRecordTest extends TwoMunicipalityTestCase
{
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-02-16 10:00:00'));
        $this->ids = $this->tenant(function (): array {
            $year = SchoolYear::query()->create(['id' => Str::uuid()->toString(), 'year' => 2026, 'start_date' => '2026-02-02', 'end_date' => '2026-12-18', 'status' => 'active']);
            $period = AcademicPeriod::query()->where('school_year_id', $year->id)->where('number', 1)->firstOrFail();
            $class = $this->createClass($year->id, '1º Ano A');
            $other = $this->createClass($year->id, '1º Ano B');
            $teacher = StaffMember::query()->create(['id' => Str::uuid()->toString(), 'school_id' => self::ESCOLA_A1, 'role' => 'teacher', 'status' => 'active', 'full_name' => 'Docente Fictício', 'institutional_email' => 'docente-aula@example.invalid']);
            ClassTeacherAssignment::query()->create(['id' => Str::uuid()->toString(), 'staff_member_id' => $teacher->id, 'school_class_id' => $class->id, 'starts_on' => '2026-02-02', 'is_main' => true]);
            $components = CurricularComponent::query()->where('active', true)->orderBy('display_order')->take(2)->pluck('id');

            return ['year' => $year->id, 'period' => $period->id, 'class' => $class->id, 'other' => $other->id,
                'component' => $components[0], 'component2' => $components[1]];
        });
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function createClass(string $year, string $name): SchoolClass
    {
        return SchoolClass::query()->create(['id' => Str::uuid()->toString(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year, 'name' => $name, 'stage' => 'Ensino Fundamental - anos iniciais', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active']);
    }

    private function login(string $role, array $scopes = [], bool $twoFactor = true, ?string $email = null): void
    {
        $user = $this->criarUsuario($email ?? Str::uuid()->toString().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $scopes);
        if (! $twoFactor) {
            $this->actingAs($user);

            return;
        }
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at($secret, now()->getTimestamp())])->assertRedirect();
    }

    private function base(?string $class = null): string
    {
        return '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/turmas/'.($class ?? $this->ids['class']);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['data' => today()->toDateString(), 'componente' => null, 'versao' => 0,
            'conteudo' => 'Leitura e interpretação de texto fictício', 'atividade' => 'Atividade fictícia',
            'bncc' => 'EF01LP01, EI03ET01'], $changes);
    }

    public function test_professor_registra_atualiza_e_nao_sobrescreve_versao_antiga(): void
    {
        $this->login('teacher', [self::ESCOLA_A1], email: 'docente-aula@example.invalid');
        $url = $this->base().'/conteudo-da-aula';
        $this->get($this->base().'/chamada')->assertOk()->assertSee('Conteúdo da aula')->assertSee('Não registre nomes nem situações de alunos aqui');
        $this->post($url, $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $this->payload(['versao' => 1, 'conteudo' => 'Texto pedagógico fictício revisado']))->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $this->payload(['versao' => 1]))->assertSessionHasErrors('versao', null, 'lesson');
        $this->post($url, $this->payload(['versao' => 2, 'conteudo' => 'Texto pedagógico fictício revisado']))->assertRedirect()->assertSessionHasNoErrors();
        $this->tenant(function (): void {
            $r = LessonRecord::query()->sole();
            $this->assertSame(2, $r->version);
            $this->assertNull($r->operation_id);
            $this->assertSame(['EF01LP01', 'EI03ET01'], $r->bncc_codes);
            $this->assertSame('Texto pedagógico fictício revisado', $r->content);
            $log = AuditLog::query()->where('action', 'lesson_record.updated')->sole();
            $this->assertSame(2, $log->after['version']);
            $this->assertStringContainsString('content', $log->after['changed_fields']);
            $this->assertStringNotContainsString('Texto pedagógico fictício', json_encode($log->after));
        });
    }

    public function test_permissoes_e_segundo_fator(): void
    {
        $url = $this->base().'/conteudo-da-aula';
        $this->login('teacher', [self::ESCOLA_A1], email: 'docente-aula@example.invalid');
        $this->post($this->base($this->ids['other']).'/conteudo-da-aula', $this->payload())->assertForbidden();
        $this->get($this->base($this->ids['other']).'/conteudos-ministrados')->assertForbidden();
        $this->login('school_director', [self::ESCOLA_A2]);
        $this->post($url, $this->payload())->assertForbidden();
        $this->login('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->base().'/conteudos-ministrados')->assertOk();
        $this->post($url, $this->payload())->assertForbidden();
        $this->login('municipal_manager');
        $this->get($this->base().'/conteudos-ministrados')->assertOk();
        $this->post($url, $this->payload())->assertForbidden();
        $this->login('school_director', [self::ESCOLA_A1], false);
        $this->post($url, $this->payload())->assertForbidden();
    }

    public function test_datas_motivos_bncc_e_fechamento(): void
    {
        $this->login('teacher', [self::ESCOLA_A1], email: 'docente-aula@example.invalid');
        $url = $this->base().'/conteudo-da-aula';
        $this->post($url, $this->payload(['data' => '2026-02-17']))->assertSessionHasErrors('data', null, 'lesson');
        $this->post($url, $this->payload(['data' => '2026-01-01', 'motivo' => 'Ajuste pedagógico fictício']))->assertSessionHasErrors('data', null, 'lesson');
        $this->post($url, $this->payload(['data' => '2026-02-15']))->assertSessionHasErrors('motivo', null, 'lesson');
        $this->post($url, $this->payload(['bncc' => 'INVALIDO']))->assertSessionHasErrors('bncc', null, 'lesson');
        $this->post($url, $this->payload(['bncc' => implode(' ', array_fill(0, 11, 'EF01LP01'))]))->assertSessionHasErrors('bncc', null, 'lesson');
        $this->post($url, $this->payload(['operation_id' => Str::uuid()->toString()]))->assertSessionHasErrors('operation_id', null, 'lesson');
        $this->post($url, $this->payload(['data' => '2026-02-15', 'motivo' => 'Registro pedagógico anterior']))->assertRedirect()->assertSessionHasNoErrors();
        $this->login('school_director', [self::ESCOLA_A1]);
        $this->post($url, $this->payload())->assertSessionHasErrors('motivo', null, 'lesson');
        $this->post($url, $this->payload(['motivo' => 'Conferência pedagógica fictícia']))->assertRedirect()->assertSessionHasNoErrors();
        $this->tenant(function (): void {
            ClassYearClosure::query()->whereKey($this->ids['class'])->update([
                'closed_at' => now(), 'closed_by' => $this->auditFixtureUser->id, 'close_reason' => 'Fechamento fictício de teste',
            ]);
        });
        $this->post($url, $this->payload(['versao' => 1, 'motivo' => 'Correção pedagógica fictícia']))->assertSessionHasErrors('conteudo', null, 'lesson');
        $this->get($this->base().'/chamada')->assertOk()->assertSee('Ano letivo encerrado')->assertDontSee('Salvar conteúdo');
        $this->get($this->base().'/conteudos-ministrados')->assertOk()->assertSee('Leitura e interpretação de texto fictício');
    }

    public function test_unicidade_relatorio_em_lote_e_painel(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);
        $url = $this->base().'/conteudo-da-aula';
        foreach ([null, $this->ids['component'], $this->ids['component2']] as $component) {
            $this->post($url, $this->payload(['componente' => $component, 'motivo' => 'Conferência pedagógica fictícia']))->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post($url, $this->payload(['motivo' => 'Conferência pedagógica fictícia']))->assertSessionHasErrors('versao', null, 'lesson');
        $this->tenant(function (): void {
            $class = SchoolClass::query()->with('schoolYear')->findOrFail($this->ids['class']);
            $period = AcademicPeriod::query()->findOrFail($this->ids['period']);
            $diary = app(LessonDiary::class);
            $db = DB::connection('tenant');
            $db->enableQueryLog();
            try {
                $db->flushQueryLog();
                $first = $diary->report($class, $period);
                $queries = count($db->getQueryLog());
                $copy = LessonRecord::query()->whereNotNull('curricular_component_id')->firstOrFail()->getAttributes();
                foreach (['2026-02-10', '2026-02-11', '2026-02-12'] as $date) {
                    $copy['id'] = Str::uuid()->toString();
                    $copy['lesson_date'] = $date;
                    $copy['bncc_codes'] = [];
                    LessonRecord::query()->create($copy);
                }
                $db->flushQueryLog();
                $second = $diary->report($class, $period);
                $this->assertSame($queries, count($db->getQueryLog()));
            } finally {
                $db->disableQueryLog();
            }
            $this->assertCount(3, $first);
            $this->assertCount(6, $second);
            $panel = app(PedagogicalPanel::class)->forYear($class->schoolYear);
            $row = collect($panel['schools'])->flatMap(fn ($s) => $s['classes'])->first(fn ($r) => $r['class']->id === $class->id);
            $this->assertSame(4, $row['periods'][0]['com_conteudo']);
            $this->assertSame(0, $row['periods'][0]['dadas']);
        });
        $this->get($this->base().'/conteudos-ministrados?periodo='.$this->ids['period'])->assertOk()->assertSee('Conteúdos ministrados')->assertSee('Leitura e interpretação de texto fictício');
        $this->tenant(function (): void {
            $record = LessonRecord::query()->whereNull('curricular_component_id')->firstOrFail();
            $copy = $record->replicate();
            $copy->id = Str::uuid()->toString();
            try {
                $copy->save();
                $this->fail('A unicidade do registro polivalente deve ser garantida no banco.');
            } catch (QueryException $e) {
                $this->assertSame('23505', $e->getCode());
            }
        });
    }
}
