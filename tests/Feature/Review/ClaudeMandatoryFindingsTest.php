<?php

namespace Tests\Feature\Review;

use App\Models\Platform\PlatformAuditLog;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\TwoMunicipalityTestCase;

class ClaudeMandatoryFindingsTest extends TwoMunicipalityTestCase
{
    public function test_c1_nomes_legitimos_nao_sao_mascarados_na_auditoria(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            foreach (['Maria Aparecida dos Santos', 'Claudomiro Pereira', 'Lucidio Alves', 'Ana Tokenaga'] as $name) {
                $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name]);
                $log = AuditLog::query()->where('auditable_id', $student->id)->where('action', 'created')->firstOrFail();
                $this->assertSame($name, $log->after['full_name']);
            }

            foreach (['EMEF Cidade Nova', 'Escola do Cidadão'] as $name) {
                $school = School::query()->create(['id' => (string) Str::uuid(), 'name' => $name, 'status' => 'active']);
                $log = AuditLog::query()->where('auditable_id', $school->id)->where('action', 'created')->firstOrFail();
                $this->assertSame($name, $log->after['name']);
            }
        });
    }

    public function test_c2_comandos_em_lote_nao_contornam_auditoria_de_registros_escolares(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = $this->createYear();
            $class = $this->createClass($year);
            $payload = [
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => 'Turma de importação fictícia', 'stage' => 'Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã',
            ];

            $operations = [
                'update' => fn () => SchoolClass::query()->whereKey($class->id)->update(['name' => 'Sem auditoria']),
                'upsert' => fn () => SchoolClass::query()->upsert([$payload], ['id'], ['name']),
                'insert' => fn () => SchoolClass::query()->insert($payload),
                'insertOrIgnore' => fn () => SchoolClass::query()->insertOrIgnore($payload),
                'insertGetId' => fn () => SchoolClass::query()->insertGetId($payload),
                'increment' => fn () => SchoolClass::query()->whereKey($class->id)->increment('capacity'),
                'decrement' => fn () => SchoolClass::query()->whereKey($class->id)->decrement('capacity'),
                'updateOrInsert' => fn () => SchoolClass::query()->updateOrInsert(['id' => $class->id], ['name' => 'Sem auditoria']),
            ];

            foreach ($operations as $operation => $callback) {
                try {
                    $callback();
                    $this->fail("A operação {$operation} não pode gravar sem auditoria.");
                } catch (LogicException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }

            $this->assertSame('Turma Monte Sereno', $class->fresh()->name);
        });
    }

    public function test_c2_metodos_silenciosos_e_sem_eventos_sao_bloqueados(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $class = $this->createClass($this->createYear());
            $operations = [
                'saveQuietly' => function () use ($class): void {
                    $class->name = 'Alteração silenciosa';
                    $class->saveQuietly();
                },
                'updateQuietly' => fn () => $class->updateQuietly(['name' => 'Alteração silenciosa']),
                'deleteQuietly' => fn () => $class->deleteQuietly(),
                'restoreQuietly' => fn () => $class->restoreQuietly(),
                'withoutEvents' => fn () => Model::withoutEvents(fn () => $class->update(['name' => 'Alteração sem eventos'])),
            ];

            foreach ($operations as $method => $callback) {
                try {
                    $callback();
                    $this->fail("O método {$method} não pode ignorar a auditoria.");
                } catch (LogicException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }

                $class->refresh();
            }

            $this->assertSame('Turma Monte Sereno', $class->name);
        });
    }

    public function test_c2_restore_em_lote_nao_pode_contornar_auditoria(): void
    {
        $actor = $this->criarUsuario('restauracao-lote@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $class = $this->createClass($this->createYear());
            $class->deleteWithReason('Arquivamento fictício', $actor->id);

            $this->expectException(LogicException::class);
            SchoolClass::withTrashed()->whereKey($class->id)->restore();
        });
    }

    public function test_c2_touch_do_builder_nao_altera_exclusao_ou_datas_sem_auditoria(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = $this->createYear();
            foreach ([fn () => SchoolYear::query()->touch(), fn () => SchoolYear::query()->touch('deleted_at')] as $operation) {
                try {
                    $operation();
                    $this->fail('O Builder::touch não pode atualizar registros escolares sem auditoria.');
                } catch (LogicException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }

            $this->assertFalse($year->fresh()->trashed());
        });
    }

    public function test_c2_eventos_ausentes_ou_listeners_removidos_impedem_gravacao(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = $this->createYear();
            $dispatcher = SchoolYear::getEventDispatcher();
            try {
                SchoolYear::unsetEventDispatcher();
                $this->expectException(LogicException::class);
                $year->update(['status' => 'closed']);
            } finally {
                SchoolYear::setEventDispatcher($dispatcher);
            }
        });
    }

    public function test_c2_flush_de_listeners_de_auditoria_impede_alteracao(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $year = $this->createYear();
            SchoolYear::flushEventListeners();
            try {
                $this->expectException(LogicException::class);
                $year->update(['status' => 'closed']);
            } finally {
                SchoolYear::clearBootedModels();
            }
        });
    }

    public function test_c2_cancelamento_por_observer_nao_deixa_metadados_sem_auditoria(): void
    {
        $actor = $this->criarUsuario('observer-deletar@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->createYear();
            SchoolYear::deleting(fn () => false);

            try {
                $this->expectException(LogicException::class);
                $year->deleteWithReason('Tentativa cancelada', $actor->id);
            } finally {
                SchoolYear::flushEventListeners();
                SchoolYear::clearBootedModels();
                $this->assertNull(SchoolYear::query()->findOrFail($year->id)->deleted_by);
            }
        });
    }

    public function test_i1_banco_municipal_impede_update_e_delete_diretos_de_auditoria(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $school = School::query()->findOrFail(self::ESCOLA_A1);
            $audit = AuditLog::query()->where('auditable_id', $school->id)->firstOrFail();

            foreach (['update', 'delete'] as $operation) {
                try {
                    if ($operation === 'update') {
                        DB::connection('tenant')->table('audit_logs')->where('id', $audit->id)->update(['action' => 'forged']);
                    } else {
                        DB::connection('tenant')->table('audit_logs')->where('id', $audit->id)->delete();
                    }

                    $this->fail("O banco aceitou {$operation} de auditoria.");
                } catch (QueryException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }
            }

            $this->assertSame('created', AuditLog::query()->findOrFail($audit->id)->action);
        });
    }

    public function test_i1_banco_central_impede_update_e_delete_diretos_de_auditoria(): void
    {
        $user = $this->criarUsuario('auditoria-plataforma@example.invalid');
        $this->post('/login', ['email' => $user->email, 'password' => 'SenhaForteDeTeste123!'])->assertRedirect('/');
        $audit = PlatformAuditLog::query()->where('action', 'auth.login')->firstOrFail();

        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    DB::connection('platform')->table('platform_audit_logs')->where('id', $audit->id)->update(['action' => 'forged']);
                } else {
                    DB::connection('platform')->table('platform_audit_logs')->where('id', $audit->id)->delete();
                }
                $this->fail("O banco central aceitou {$operation} de auditoria.");
            } catch (QueryException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }

        $this->assertSame('auth.login', PlatformAuditLog::query()->findOrFail($audit->id)->action);
    }

    public function test_i1_upsert_de_auditoria_nao_pode_alterar_evento(): void
    {
        app(TenantContext::class)->runFor($this->municipioA, function (): void {
            $log = AuditLog::query()->where('auditable_id', self::ESCOLA_A1)->firstOrFail();
            $this->expectException(LogicException::class);
            AuditLog::query()->upsert([['id' => $log->id, 'action' => 'forged']], ['id'], ['action']);
        });
    }

    public function test_i2_pais_com_dependentes_nao_podem_ser_excluidos(): void
    {
        $actor = $this->criarUsuario('integridade-pais@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->createYear();
            $this->createClass($year);

            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Aluna fictícia Monte Sereno']);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'status' => 'active',
            ]);

            $parents = [School::query()->findOrFail(self::ESCOLA_A1), $year, $student];
            foreach ($parents as $parent) {
                try {
                    $parent->deleteWithReason('Verificação de integridade', $actor->id);
                    $this->fail('O registro pai com dependentes não pode ser arquivado.');
                } catch (LogicException $exception) {
                    $this->assertNotEmpty($exception->getMessage());
                }

                $this->assertFalse($parent->fresh()->trashed());
            }
        });
    }

    public function test_i2_nao_restaura_turma_com_ano_letivo_arquivado(): void
    {
        $actor = $this->criarUsuario('restaura-filho@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->createYear();
            $class = $this->createClass($year);
            $class->deleteWithReason('Conferência histórica', $actor->id);
            $year->deleteWithReason('Conferência histórica', $actor->id);

            try {
                $class->restoreWithReason('Rever turma', $actor->id);
                $this->fail('Turma não pode ser restaurada com o ano arquivado.');
            } catch (LogicException) {
                $this->assertTrue(SchoolClass::withTrashed()->findOrFail($class->id)->trashed());
            }
        });
    }

    public function test_i2_nova_turma_nao_pode_ser_criada_em_ano_letivo_arquivado(): void
    {
        $actor = $this->criarUsuario('matricula-ano-arquivado@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->createYear();
            $year->deleteWithReason('Fechamento de ano fictício', $actor->id);

            $this->expectException(LogicException::class);
            $this->createClass($year);
        });
    }

    public function test_i2_matricula_arquivada_respeita_pais_e_mantem_historico(): void
    {
        $actor = $this->criarUsuario('integridade-matricula@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->createYear();
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Estudante fictício']);
            $enrollment = StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $student->id,
                'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id, 'status' => 'active',
            ]);
            $enrollment->deleteWithReason('Revisão', $actor->id);
            $student->deleteWithReason('Revisão', $actor->id);

            try {
                $enrollment->restoreWithReason('Conferência', $actor->id);
                $this->fail('Matrícula não pode ser restaurada com aluno arquivado.');
            } catch (LogicException) {
                $this->assertTrue(StudentEnrollment::withTrashed()->findOrFail($enrollment->id)->trashed());
            }

            $student->restoreWithReason('Identidade conferida', $actor->id);
            $enrollment->restoreWithReason('Vínculo conferido', $actor->id);
            $this->assertSame($student->id, $enrollment->fresh()->student->id);
        });
    }

    public function test_i3_restauracao_exige_autor_motivo_e_registra_ambos(): void
    {
        $actor = $this->criarUsuario('autor-restauracao@example.invalid');
        app(TenantContext::class)->runFor($this->municipioA, function () use ($actor): void {
            $year = $this->createYear();
            $year->deleteWithReason('Duplicidade do ano', $actor->id);

            try {
                $year->restore();
                $this->fail('Restauração sem autoria e motivo é proibida.');
            } catch (LogicException) {
                $this->assertTrue(SchoolYear::withTrashed()->findOrFail($year->id)->trashed());
            }

            $year->restoreWithReason('Registro escolar conferido', $actor->id);
            $log = AuditLog::query()->where('auditable_id', $year->id)->where('action', 'restored')->firstOrFail();
            $this->assertSame($actor->id, $log->user_id);
            $this->assertSame($actor->id, $log->after['restored_by']);
            $this->assertSame('Registro escolar conferido', $log->after['restoration_reason']);
            $this->assertSame('Duplicidade do ano', $year->fresh()->deletion_reason);
        });
    }

    private function createYear(): SchoolYear
    {
        return SchoolYear::query()->create([
            'id' => (string) Str::uuid(), 'year' => 2027,
            'start_date' => '2027-02-01', 'end_date' => '2027-12-17', 'status' => 'active',
        ]);
    }

    private function createClass(SchoolYear $year): SchoolClass
    {
        return SchoolClass::query()->create([
            'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
            'name' => 'Turma Monte Sereno', 'stage' => 'Fundamental', 'modality' => 'Regular',
            'shift' => 'Manhã', 'status' => 'active',
        ]);
    }
}
