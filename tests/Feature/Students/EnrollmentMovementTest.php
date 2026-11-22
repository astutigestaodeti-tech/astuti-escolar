<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class EnrollmentMovementTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $yearId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yearId = $this->tenant(fn (): string => SchoolYear::query()->create([
            'id' => (string) Str::uuid(),
            'year' => 2027,
            'start_date' => '2027-02-01',
            'end_date' => '2027-12-18',
            'status' => 'active',
        ])->id);
    }

    private function tenant(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function account(string $email, string $role, array $schools = [], bool $twoFactor = true): User
    {
        $user = $this->criarUsuario($email);
        $this->vincular($user, $this->municipioA, $role, $schools);

        if ($twoFactor) {
            $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        }

        return $user;
    }

    private function confirmed(User $user): void
    {
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    /** @return array{student: string, enrollment: string, version: string} */
    private function fixture(string $school = self::ESCOLA_A1, string $name = 'Alice Fictícia de Teste', ?string $started = '2027-02-03'): array
    {
        return $this->tenant(function () use ($school, $name, $started): array {
            $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name, 'birth_date' => '2018-04-05']);
            $enrollment = StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $student->id,
                'school_id' => $school,
                'school_year_id' => $this->yearId,
                'status' => 'active',
                'started_at' => $started,
            ]);

            $row = StudentEnrollment::query()->selectRaw('student_enrollments.*, xmin::text AS row_token')
                ->findOrFail($enrollment->id);

            return ['student' => $student->id, 'enrollment' => $enrollment->id, 'version' => (string) $row->row_token];
        });
    }

    private function actionUrl(array $fixture, string $action, string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.$fixture['student'].'/matriculas/'.$fixture['enrollment'].'/'.$action;
    }

    private function transferData(array $fixture, array $override = []): array
    {
        return array_replace([
            'versao' => $fixture['version'],
            'escola_destino_id' => self::ESCOLA_A2,
            'data' => '2027-04-10',
            'confirmar' => '1',
        ], $override);
    }

    public function test_transferencia_muda_as_duas_matriculas_na_mesma_rede_com_auditoria_e_listas_corretas(): void
    {
        $fixture = $this->fixture();
        $user = $this->account('secretaria-transferencia@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->confirmed($user);

        $url = $this->actionUrl($fixture, 'transferir');
        $this->get($url)->assertOk()->assertSee('Outra Unidade A')
            ->assertSee('name="versao" value="'.$fixture['version'].'"', false);
        $this->post($url, $this->transferData($fixture))->assertRedirect('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas');

        $this->tenant(function () use ($fixture, $user): void {
            $old = StudentEnrollment::query()->findOrFail($fixture['enrollment']);
            $new = StudentEnrollment::query()->where('student_id', $fixture['student'])->where('school_id', self::ESCOLA_A2)->firstOrFail();

            $this->assertSame('transferred', $old->status);
            $this->assertSame('2027-04-10', $old->ended_at->format('Y-m-d'));
            $this->assertSame('active', $new->status);
            $this->assertSame('2027-04-10', $new->started_at->format('Y-m-d'));
            $this->assertSame($old->school_year_id, $new->school_year_id);

            $log = AuditLog::query()->where('auditable_id', $old->id)->where('action', 'updated')->firstOrFail();
            $this->assertSame('active', $log->before['status']);
            $this->assertSame('transferred', $log->after['status']);
            $this->assertSame($user->id, $log->user_id);
            $this->assertSame(1, AuditLog::query()->where('auditable_id', $new->id)->where('action', 'created')->count());
        });

        $manager = $this->account('gestor-confere-transferencia@example.invalid', 'municipal_manager');
        $this->confirmed($manager);
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')->assertDontSee('Alice Fictícia de Teste');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos')->assertSee('Alice Fictícia de Teste');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$fixture['student'])->assertNotFound();
    }

    public function test_transferencia_recusa_propria_escola_e_destino_ja_ativo_sem_gravacao_parcial(): void
    {
        $fixture = $this->fixture();
        $user = $this->account('gestor-transferencia@example.invalid', 'municipal_manager');
        $this->confirmed($user);

        $url = $this->actionUrl($fixture, 'transferir');
        $this->post($url, $this->transferData($fixture, ['escola_destino_id' => self::ESCOLA_A1]))->assertSessionHasErrors('escola_destino_id');
        $this->tenant(function () use ($fixture): void {
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $fixture['student'],
                'school_id' => self::ESCOLA_A2, 'school_year_id' => $this->yearId,
                'status' => 'active', 'started_at' => '2027-02-03',
            ]);
        });
        $this->post($url, $this->transferData($fixture))->assertSessionHasErrors('escola_destino_id');

        $this->tenant(function () use ($fixture): void {
            $this->assertSame('active', StudentEnrollment::query()->findOrFail($fixture['enrollment'])->status);
            $this->assertSame(2, StudentEnrollment::query()->where('student_id', $fixture['student'])->count());
        });
    }

    public function test_transferencia_e_encerramento_validam_datas_do_ano_e_inicio_do_vinculo(): void
    {
        $fixture = $this->fixture(started: '2027-03-01');
        $user = $this->account('datas-matricula@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->confirmed($user);

        $url = $this->actionUrl($fixture, 'transferir');
        foreach (['2027-01-31', '2027-02-28', '2027-12-19'] as $date) {
            $this->post($url, $this->transferData($fixture, ['data' => $date]))->assertSessionHasErrors('data');
        }

        $endUrl = $this->actionUrl($fixture, 'encerrar');
        $this->post($endUrl, ['versao' => $fixture['version'], 'situacao' => 'abandoned', 'data' => '2027-02-28', 'confirmar' => '1'])
            ->assertSessionHasErrors('data');
        $this->post($endUrl, ['versao' => $fixture['version'], 'situacao' => 'invalid', 'data' => '2027-04-10', 'confirmar' => '1'])
            ->assertSessionHasErrors('situacao');

        $this->tenant(fn () => $this->assertSame('active', StudentEnrollment::query()->findOrFail($fixture['enrollment'])->status));
    }

    public function test_encerramento_aceita_o_ultimo_dia_do_ano_letivo_e_o_mesmo_dia_do_inicio(): void
    {
        $user = $this->account('diretora-datas-limites@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->confirmed($user);

        foreach (['2027-02-01', '2027-12-18'] as $date) {
            $fixture = $this->fixture(started: '2027-02-01');
            $this->post($this->actionUrl($fixture, 'encerrar'), [
                'versao' => $fixture['version'], 'situacao' => 'abandoned', 'data' => $date, 'confirmar' => '1',
            ])->assertRedirect();
            $this->tenant(fn () => $this->assertSame($date, StudentEnrollment::query()->findOrFail($fixture['enrollment'])->ended_at->format('Y-m-d')));
        }
    }

    public function test_encerramentos_fixos_sao_auditados_sem_excluir_aluno_ou_matricula(): void
    {
        $user = $this->account('secretaria-encerramento@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        $this->confirmed($user);

        foreach (['left_network', 'abandoned', 'deceased'] as $status) {
            $fixture = $this->fixture(name: 'Aluno Fictício '.Str::random(8));
            $this->post($this->actionUrl($fixture, 'encerrar'), [
                'versao' => $fixture['version'], 'situacao' => $status, 'data' => '2027-04-10',
                'destino' => $status === 'left_network' ? 'Outra rede fictícia' : 'Destino ignorado',
                'confirmar' => '1',
            ])->assertRedirect('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/matriculas');

            $this->tenant(function () use ($fixture, $status, $user): void {
                $row = StudentEnrollment::query()->findOrFail($fixture['enrollment']);
                $this->assertSame($status, $row->status);
                $this->assertSame('2027-04-10', $row->ended_at->format('Y-m-d'));
                $destination = DB::connection('tenant')->table('student_enrollment_destinations')
                    ->where('enrollment_id', $fixture['enrollment'])->value('destination_name');
                $this->assertSame($status === 'left_network' ? 'Outra rede fictícia' : null, $destination);
                $this->assertNotNull(Student::query()->find($fixture['student']));
                $log = AuditLog::query()->where('auditable_id', $fixture['enrollment'])->where('action', 'updated')->firstOrFail();
                $this->assertSame('active', $log->before['status']);
                $this->assertSame($status, $log->after['status']);
                $this->assertSame($user->id, $log->user_id);
                $noteLog = AuditLog::query()->where('auditable_id', $fixture['enrollment'])
                    ->where('action', 'enrollment.exit_destination_recorded')->first();
                $this->assertSame($status === 'left_network' ? 'Outra rede fictícia' : null, $noteLog?->after['destination_note'] ?? null);
            });
        }
    }

    public function test_exclusao_justificada_arquiva_matricula_e_aluno_sem_outros_vinculos(): void
    {
        $fixture = $this->fixture();
        $user = $this->account('diretor-exclusao@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->confirmed($user);

        $url = $this->actionUrl($fixture, 'excluir-cadastro');
        $this->get($url)->assertOk()->assertSee('Excluir cadastro');
        $this->delete($url, ['versao' => $fixture['version'], 'motivo' => 'Cadastro fictício feito em duplicidade', 'confirmar' => '1'])
            ->assertRedirect('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos');

        $this->tenant(function () use ($fixture, $user): void {
            $this->assertNull(Student::query()->find($fixture['student']));
            $this->assertNull(StudentEnrollment::query()->find($fixture['enrollment']));
            $enrollment = StudentEnrollment::withTrashed()->findOrFail($fixture['enrollment']);
            $student = Student::withTrashed()->findOrFail($fixture['student']);
            $this->assertSame($user->id, $enrollment->deleted_by);
            $this->assertSame($user->id, $student->deleted_by);
            $this->assertSame('Cadastro fictício feito em duplicidade', $student->deletion_reason);
            $enrollmentLog = AuditLog::query()->where('auditable_id', $fixture['enrollment'])->where('action', 'deleted')->firstOrFail();
            $studentLog = AuditLog::query()->where('auditable_id', $fixture['student'])->where('action', 'deleted')->firstOrFail();
            $this->assertNull($enrollmentLog->before['deleted_at']);
            $this->assertSame('Cadastro fictício feito em duplicidade', $enrollmentLog->after['deletion_reason']);
            $this->assertSame($user->id, $enrollmentLog->user_id);
            $this->assertSame('Cadastro fictício feito em duplicidade', $studentLog->after['deletion_reason']);
            $this->assertSame($user->id, $studentLog->user_id);
        });
    }

    public function test_exclusao_preserva_aluno_com_outro_vinculo_e_recusa_justificativa_curta(): void
    {
        $fixture = $this->fixture();
        $user = $this->account('gestor-exclusao@example.invalid', 'municipal_manager');
        $this->confirmed($user);
        $url = $this->actionUrl($fixture, 'excluir-cadastro');

        $this->delete($url, ['versao' => $fixture['version'], 'motivo' => 'Curto', 'confirmar' => '1'])->assertSessionHasErrors('motivo');
        $this->tenant(function () use ($fixture): void {
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $fixture['student'],
                'school_id' => self::ESCOLA_A2, 'school_year_id' => $this->yearId,
                'status' => 'completed', 'started_at' => '2027-02-01', 'ended_at' => '2027-03-01',
            ]);
        });

        $this->delete($url, ['versao' => $fixture['version'], 'motivo' => 'Cadastro fictício criado incorretamente', 'confirmar' => '1'])->assertRedirect();
        $this->tenant(function () use ($fixture): void {
            $this->assertNotNull(Student::query()->find($fixture['student']));
            $this->assertNull(StudentEnrollment::query()->find($fixture['enrollment']));
            $this->assertSame(1, StudentEnrollment::query()->where('student_id', $fixture['student'])->count());
        });
    }

    public function test_destino_externo_nao_desaparece_por_exclusao_fisica_em_cascata(): void
    {
        $fixture = $this->fixture();

        $this->tenant(function () use ($fixture): void {
            DB::connection('tenant')->table('student_enrollment_destinations')->insert([
                'enrollment_id' => $fixture['enrollment'],
                'destination_name' => 'Outra rede fictícia',
                'created_at' => now(),
            ]);

            try {
                DB::connection('tenant')->table('student_enrollments')
                    ->where('id', $fixture['enrollment'])->delete();
                $this->fail('A chave estrangeira deve recusar a exclusão física do histórico.');
            } catch (QueryException $exception) {
                $this->assertSame('23503', $exception->getCode());
            }

            $this->assertSame(1, DB::connection('tenant')->table('student_enrollment_destinations')
                ->where('enrollment_id', $fixture['enrollment'])->count());
        });
    }

    public function test_perfis_escolas_e_segundo_fator_sao_verificados_nas_tres_operacoes(): void
    {
        $fixture = $this->fixture();
        $teacher = $this->account('professor-movimentos@example.invalid', 'teacher', [self::ESCOLA_A1]);
        $otherSchool = $this->account('secretaria-outra@example.invalid', 'school_secretary', [self::ESCOLA_A2]);
        $secretary = $this->account('secretaria-sem-exclusao@example.invalid', 'school_secretary', [self::ESCOLA_A1]);
        // Gestor pode executar as três operações, mas nunca sem o segundo fator.
        // A secretária é proibida de excluir mesmo antes do desafio (403).
        $noFactor = $this->account('gestor-sem-fator@example.invalid', 'municipal_manager', [], false);

        foreach (['transferir', 'encerrar', 'excluir-cadastro'] as $action) {
            // O TOTP é de uso único por etapa de 30 segundos; uma nova rodada
            // precisa de um código realmente novo para cada conta.
            $this->travel(31)->seconds();
            $url = $this->actionUrl($fixture, $action);
            $data = ['versao' => $fixture['version'], 'data' => '2027-04-10', 'escola_destino_id' => self::ESCOLA_A2,
                'situacao' => 'abandoned', 'motivo' => 'Motivo fictício suficiente', 'confirmar' => '1'];

            $this->confirmed($teacher);
            $this->get($url)->assertForbidden();
            if ($action === 'excluir-cadastro') {
                $this->delete($url, $data)->assertForbidden();
            } else {
                $this->post($url, $data)->assertForbidden();
            }

            $this->confirmed($otherSchool);
            $this->get($url)->assertForbidden();
            if ($action === 'excluir-cadastro') {
                $this->delete($url, $data)->assertForbidden();
            } else {
                $this->post($url, $data)->assertForbidden();
            }

            $this->actingAs($noFactor);
            $this->get($url)->assertRedirect('/seguranca/segundo-fator/desafio');
            if ($action === 'excluir-cadastro') {
                $this->delete($url, $data)->assertForbidden();
            } else {
                $this->post($url, $data)->assertForbidden();
            }
        }

        $this->confirmed($secretary);
        $this->get($this->actionUrl($fixture, 'excluir-cadastro'))->assertForbidden();
        $this->delete($this->actionUrl($fixture, 'excluir-cadastro'), [
            'versao' => $fixture['version'], 'motivo' => 'Motivo fictício suficiente', 'confirmar' => '1',
        ])->assertForbidden();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$fixture['student'])
            ->assertSee('Transferir matrícula')->assertSee('Encerrar matrícula')->assertDontSee('Excluir cadastro');
        $this->tenant(fn () => $this->assertSame('active', StudentEnrollment::query()->findOrFail($fixture['enrollment'])->status));
    }

    public function test_aluno_de_outra_escola_retorna_404_em_cada_acao(): void
    {
        $fixture = $this->fixture(school: self::ESCOLA_A2);
        $manager = $this->account('gestor-nao-encontra@example.invalid', 'municipal_manager');
        $this->confirmed($manager);

        foreach (['transferir', 'encerrar', 'excluir-cadastro'] as $action) {
            $url = $this->actionUrl($fixture, $action, self::ESCOLA_A1);
            $this->get($url)->assertNotFound();
            if ($action === 'excluir-cadastro') {
                $this->delete($url, $this->transferData($fixture))->assertNotFound();
            } else {
                $this->post($url, $this->transferData($fixture))->assertNotFound();
            }
        }
    }

    public function test_alteracao_no_mesmo_segundo_recusa_formulario_antigo_nas_tres_operacoes(): void
    {
        $this->freezeTime();
        $manager = $this->account('gestor-versao-mesmo-segundo@example.invalid', 'municipal_manager');
        $this->confirmed($manager);

        foreach (['transferir', 'encerrar', 'excluir-cadastro'] as $action) {
            $fixture = $this->fixture(name: 'Aluno Concorrência de Segundos '.Str::random(8));

            $this->tenant(function () use ($fixture): void {
                $enrollment = StudentEnrollment::query()->findOrFail($fixture['enrollment']);
                $createdSecond = $enrollment->updated_at->format('Y-m-d H:i:s');
                $enrollment->started_at = '2027-02-04';
                $enrollment->save();

                // Dois UPDATEs no mesmo segundo têm timestamp idêntico,
                // mas o PostgreSQL muda o xmin da linha.
                $this->assertSame($createdSecond, $enrollment->fresh()->updated_at->format('Y-m-d H:i:s'));
            });

            $url = $this->actionUrl($fixture, $action);
            $data = array_merge($this->transferData($fixture), [
                'situacao' => 'abandoned',
                'motivo' => 'Cadastro fictício alterado por outra pessoa',
            ]);

            if ($action === 'excluir-cadastro') {
                $this->delete($url, $data)->assertSessionHasErrors('versao');
            } else {
                $this->post($url, $data)->assertSessionHasErrors('versao');
            }

            $this->tenant(fn () => $this->assertSame('active', StudentEnrollment::query()->findOrFail($fixture['enrollment'])->status));
        }
    }

    public function test_versao_antiga_impede_transferencia_encerramento_e_exclusao(): void
    {
        $manager = $this->account('gestor-versao@example.invalid', 'municipal_manager');
        $this->confirmed($manager);

        foreach (['transferir', 'encerrar', 'excluir-cadastro'] as $action) {
            $fixture = $this->fixture(name: 'Aluno Concorrência Fictício '.Str::random(8));
            $this->travel(2)->seconds();
            $this->tenant(function () use ($fixture): void {
                $enrollment = StudentEnrollment::query()->findOrFail($fixture['enrollment']);
                $enrollment->started_at = '2027-02-04';
                $enrollment->save();
            });
            $url = $this->actionUrl($fixture, $action);
            $data = array_merge($this->transferData($fixture), ['situacao' => 'abandoned', 'motivo' => 'Cadastro fictício para exclusão']);

            if ($action === 'excluir-cadastro') {
                $this->delete($url, $data)->assertSessionHasErrors('versao');
            } else {
                $this->post($url, $data)->assertSessionHasErrors('versao');
            }

            $this->tenant(fn () => $this->assertSame('active', StudentEnrollment::query()->findOrFail($fixture['enrollment'])->status));
        }
    }

    public function test_exclusao_pede_codigo_recente_e_devolve_o_formulario_preenchido(): void
    {
        $fixture = $this->fixture();
        $user = $this->account('diretor-exclusao-recente@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->confirmed($user);
        $url = $this->actionUrl($fixture, 'excluir-cadastro');

        // Dentro de 15 minutos o formulário abre direto.
        $this->get($url)->assertOk()->assertSee('Excluir cadastro');

        // O formulário ficou aberto por mais de 15 minutos: gravar pede o código de novo.
        $this->travel(16)->minutes();
        $this->delete($url, ['versao' => $fixture['version'], 'motivo' => 'Cadastro fictício duplicado na escola', 'confirmar' => '1'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->tenant(fn () => $this->assertSame('active', StudentEnrollment::query()->findOrFail($fixture['enrollment'])->status));
        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Esta ação pede uma nova confirmação');

        // Depois do código, volta ao formulário com o motivo já digitado.
        $this->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])
            ->assertRedirect($url)
            ->assertSessionHasInput('motivo', 'Cadastro fictício duplicado na escola');
        $this->get($url)->assertOk()->assertSee('Cadastro fictício duplicado na escola');
        $this->assertNull(session('astuti.two_factor.pending_input'));
    }

    public function test_abrir_exclusao_depois_de_quinze_minutos_pede_codigo_antes_do_formulario(): void
    {
        $fixture = $this->fixture();
        $user = $this->account('diretor-exclusao-abrir@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->confirmed($user);
        $url = $this->actionUrl($fixture, 'excluir-cadastro');

        $this->travel(16)->minutes();
        // Transferir e encerrar seguem liberados pela verificação da entrada.
        $this->get($this->actionUrl($fixture, 'transferir'))->assertOk();
        $this->get($this->actionUrl($fixture, 'encerrar'))->assertOk();

        $this->get($url)->assertRedirect(route('two-factor.challenge'));
        $this->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])
            ->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Excluir cadastro');
    }

    public function test_gravacao_depois_do_teto_de_doze_horas_volta_ao_formulario_com_os_dados(): void
    {
        $fixture = $this->fixture();
        $user = $this->account('diretor-teto@example.invalid', 'school_director', [self::ESCOLA_A1]);
        $this->confirmed($user);
        $url = $this->actionUrl($fixture, 'transferir');

        $this->get($url)->assertOk();
        $this->travel(12)->hours();
        $this->travel(1)->minutes();
        $this->post($url, $this->transferData($fixture, ['data' => '2027-04-12']))
            ->assertRedirect(route('two-factor.challenge'));
        $this->tenant(fn () => $this->assertSame('active', StudentEnrollment::query()->findOrFail($fixture['enrollment'])->status));

        $this->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])
            ->assertRedirect($url)
            ->assertSessionHasInput('data', '2027-04-12')
            ->assertSessionHasInput('escola_destino_id', self::ESCOLA_A2)
            ->assertSessionMissing('_old_input._token');
    }
}
