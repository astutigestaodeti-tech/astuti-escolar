<?php

namespace Tests\Feature\Students;

use App\Models\Platform\Municipality;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolStudentShowTest extends TwoMunicipalityTestCase
{
    public function test_secretaria_abre_ficha_do_aluno_com_vinculo_ativo_na_escola(): void
    {
        $user = $this->criarUsuario('ficha-autorizada@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Maria da Unidade A', 'active', '2017-04-02');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertOk()
            ->assertSee('Maria da Unidade A')
            ->assertSee('2017')
            ->assertSee('2027')
            ->assertSee('Unidade A')
            ->assertSee('Somente consulta');

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_ficha_exibe_historico_da_mesma_escola_sem_revelar_vinculos_de_outra(): void
    {
        $user = $this->criarUsuario('ficha-historico@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Aluna com histórico');

        app(TenantContext::class)->runFor($this->municipioA, function () use ($studentId): void {
            $year2026 = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2026,
                'start_date' => '2026-02-01',
                'end_date' => '2026-12-18',
                'status' => 'completed',
            ]);
            $year2025 = SchoolYear::query()->create([
                'id' => (string) Str::uuid(),
                'year' => 2025,
                'start_date' => '2025-02-01',
                'end_date' => '2025-12-18',
                'status' => 'completed',
            ]);

            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'school_id' => self::ESCOLA_A1,
                'school_year_id' => $year2026->id,
                'status' => 'completed',
                'started_at' => '2026-02-03',
                'ended_at' => '2026-12-12',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $studentId,
                'school_id' => self::ESCOLA_A2,
                'school_year_id' => $year2025->id,
                'status' => 'completed',
            ]);
        });

        $response = $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertOk()
            ->assertSee('Histórico de vínculos nesta escola')
            ->assertSee('Ano letivo 2027')
            ->assertSee('Ano letivo 2026')
            ->assertSee('Concluído')
            ->assertSee('03/02/2026')
            ->assertSee('12/12/2026')
            ->assertDontSee('Ano letivo 2025');

        $this->assertMatchesRegularExpression(
            '/<div class="vinculo-datas">\\s*<small>Início: 03\\/02\\/2026<\\/small>\\s*<small>Encerramento: 12\\/12\\/2026<\\/small>\\s*<\\/div>/',
            $response->getContent(),
            'As datas do histórico devem ocupar um bloco abaixo das etiquetas, dentro do cartão.',
        );

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_ficha_nao_expoe_aluno_de_outra_escola_ou_sem_vinculo_ativo(): void
    {
        $user = $this->criarUsuario('ficha-escopo@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);

        $studentOther = $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Aluno da Outra Escola');
        $studentInactive = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Aluno Desvinculado', 'completed');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentOther)
            ->assertNotFound();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentInactive)
            ->assertNotFound();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos/'.$studentOther)
            ->assertForbidden();
        $this->get('/m/municipio-b/escolas/'.self::ESCOLA_B1.'/alunos/'.$studentOther)
            ->assertForbidden();

        $this->assertFalse(app(TenantContext::class)->hasActive());
    }

    public function test_visitante_nao_pode_abrir_ficha_de_aluno(): void
    {
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.Str::uuid())
            ->assertRedirect('/login');
    }

    public function test_nome_malicioso_e_escapado_e_lista_aponta_para_ficha(): void
    {
        $user = $this->criarUsuario('ficha-html@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, '<script>alert(1)</script>', 'active', null);

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos')
            ->assertOk()
            ->assertSee('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId, false);

        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Não informado');
    }

    public function test_consulta_autorizada_registra_autoria_sem_dados_pessoais_na_trilha_municipal(): void
    {
        $user = $this->criarUsuario('ficha-auditada@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Aluno com consulta auditada');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertOk();

        $events = app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()
            ->where('action', 'student.viewed')
            ->where('auditable_id', $studentId)
            ->get());

        $this->assertCount(1, $events);
        $this->assertSame($user->id, $events->first()->user_id);
        $this->assertSame(Student::class, $events->first()->auditable_type);
        $this->assertNull($events->first()->before);
        $this->assertNull($events->first()->after);

        $eventsOther = app(TenantContext::class)->runFor($this->municipioB, fn () => AuditLog::query()
            ->where('action', 'student.viewed')
            ->where('auditable_id', $studentId)
            ->count());
        $this->assertSame(0, $eventsOther);
    }

    public function test_ficha_negada_nao_registra_consulta_de_aluno(): void
    {
        $user = $this->criarUsuario('ficha-auditoria-negada@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A2, 'Aluno de acesso proibido');

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertNotFound();
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/alunos/'.$studentId)
            ->assertForbidden();

        $audits = app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()
            ->where('action', 'student.viewed')
            ->where('auditable_id', $studentId)
            ->count());
        $this->assertSame(0, $audits);
    }

    public function test_segundo_fator_pendente_nao_grava_auditoria_de_ficha(): void
    {
        $user = $this->criarUsuario('ficha-auditoria-totp@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $studentId = $this->matricularAluno($this->municipioA, self::ESCOLA_A1, 'Aluno para TOTP');
        $user->forceFill([
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$studentId)
            ->assertRedirect(route('two-factor.challenge'));

        $audits = app(TenantContext::class)->runFor($this->municipioA, fn () => AuditLog::query()
            ->where('action', 'student.viewed')
            ->where('auditable_id', $studentId)
            ->count());
        $this->assertSame(0, $audits);
    }

    private function matricularAluno(
        Municipality $municipio,
        string $escola,
        string $nome,
        string $status = 'active',
        ?string $nascimento = null,
    ): string {
        $id = (string) Str::uuid();

        app(TenantContext::class)->runFor($municipio, function () use ($id, $escola, $nome, $status, $nascimento): void {
            $year = SchoolYear::query()->firstOrCreate(
                ['year' => 2027],
                [
                    'id' => (string) Str::uuid(),
                    'start_date' => '2027-02-01',
                    'end_date' => '2027-12-18',
                    'status' => 'active',
                ],
            );

            Student::query()->create([
                'id' => $id,
                'full_name' => $nome,
                'birth_date' => $nascimento,
            ]);

            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(),
                'student_id' => $id,
                'school_id' => $escola,
                'school_year_id' => $year->id,
                'status' => $status,
            ]);
        });

        return $id;
    }
}
