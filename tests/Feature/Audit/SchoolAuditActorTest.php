<?php

namespace Tests\Feature\Audit;

use App\Audit\AuditActor;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Student;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolAuditActorTest extends TwoMunicipalityTestCase
{
    public function test_n3_gravacao_sem_usuario_ou_autoria_explicita_e_revertida(): void
    {
        $actor = app(AuditActor::class);
        $id = (string) Str::uuid();

        $actor->leave();
        try {
            app(TenantContext::class)->runFor($this->municipioA, function () use ($id): void {
                try {
                    Student::query()->create(['id' => $id, 'full_name' => 'Aluno inteiramente fictício']);
                    $this->fail('Gravação de aluno sem autoria não pode ser aceita.');
                } catch (LogicException $exception) {
                    $this->assertStringContainsString('autoria', $exception->getMessage());
                }

                $this->assertNull(Student::query()->find($id));
                $this->assertSame(0, AuditLog::query()->where('auditable_id', $id)->count());
            });
        } finally {
            $actor->enter($this->auditFixtureUser->id);
        }
    }

    public function test_n3_job_registra_responsavel_explicito_no_historico(): void
    {
        $user = $this->criarUsuario('responsavel-importacao@astuti.invalid');
        $actor = app(AuditActor::class);
        $id = (string) Str::uuid();

        $actor->runAs($user->id, function () use ($id, $user): void {
            app(TenantContext::class)->runFor($this->municipioA, function () use ($id, $user): void {
                Student::query()->create(['id' => $id, 'full_name' => 'Aluno importado fictício']);
                $log = AuditLog::query()->where('auditable_id', $id)->where('action', 'created')->firstOrFail();
                $this->assertSame($user->id, $log->user_id);
            });
        });

        $this->assertSame($this->auditFixtureUser->id, $actor->current());
    }
}
