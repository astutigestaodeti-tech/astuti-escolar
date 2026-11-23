<?php

namespace Tests\Feature\Documents;

use App\Documents\TranscriptFingerprint;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\AttendanceMark;
use App\Models\Tenant\AttendanceSession;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\ClassYearClosure;
use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\GradeEntry;
use App\Models\Tenant\IssuedDocument;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentFinalResult;
use App\Models\Tenant\StudentPriorSchoolYear;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class SchoolTranscriptTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private string $studentId;

    private string $currentEnrollmentId;

    private string $closedEnrollmentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2027-08-15 15:00:00'));
        [$this->studentId, $this->currentEnrollmentId, $this->closedEnrollmentId] =
            $this->tenant(function (): array {
                $student = Student::query()->create([
                    'id' => (string) Str::uuid(), 'full_name' => 'Ana Fictícia da Silva',
                    'birth_date' => '2016-03-10', 'birthplace_city' => 'Cidade Inventada',
                    'birthplace_state' => 'SP', 'ra' => '987654321-1/SP',
                ]);
                $oldYear = SchoolYear::query()->create([
                    'id' => (string) Str::uuid(), 'year' => 2026,
                    'start_date' => '2026-02-01', 'end_date' => '2026-12-16', 'status' => 'active',
                ]);
                $oldClass = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $oldYear->id,
                    'name' => '3º ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                    'shift' => 'Manhã', 'status' => 'active',
                ]);
                $past = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $student->id,
                    'school_id' => self::ESCOLA_A1, 'school_year_id' => $oldYear->id,
                    'school_class_id' => $oldClass->id, 'status' => 'completed',
                ]);
                $currentYear = SchoolYear::query()->create([
                    'id' => (string) Str::uuid(), 'year' => 2027,
                    'start_date' => '2027-02-01', 'end_date' => '2027-12-16', 'status' => 'active',
                ]);
                $currentClass = SchoolClass::query()->create([
                    'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $currentYear->id, 'name' => '4º ano A',
                    'stage' => 'Ensino Fundamental', 'modality' => 'Regular',
                    'shift' => 'Manhã', 'status' => 'active',
                ]);
                $current = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $student->id,
                    'school_id' => self::ESCOLA_A1, 'school_year_id' => $currentYear->id,
                    'school_class_id' => $currentClass->id, 'status' => 'active',
                ]);
                StudentFinalResult::query()->create([
                    'id' => (string) Str::uuid(), 'school_class_id' => $oldClass->id,
                    'student_enrollment_id' => $past->id, 'suggested_result' => 'aprovado',
                    'result' => 'aprovado', 'confirmed_by' => $this->auditFixtureUser->id,
                    'confirmed_at' => '2026-12-17 09:00:00',
                ]);
                $component = CurricularComponent::query()->where('code', 'matematica')->sole();
                $periods = AcademicPeriod::query()->where('school_year_id', $oldYear->id)
                    ->orderBy('number')->limit(2)->get();
                foreach ($periods as $index => $period) {
                    GradeEntry::query()->create([
                        'id' => (string) Str::uuid(), 'school_class_id' => $oldClass->id,
                        'student_enrollment_id' => $past->id,
                        'academic_period_id' => $period->id,
                        'curricular_component_id' => $component->id,
                        'grade' => $index === 0 ? '7.0' : '9.0',
                        'recorded_by' => $this->auditFixtureUser->id,
                        'updated_by' => $this->auditFixtureUser->id,
                    ]);
                }
                ClassYearClosure::query()->findOrFail($oldClass->id)->forceFill([
                    'closed_at' => '2026-12-17 11:00:00',
                    'closed_by' => $this->auditFixtureUser->id,
                    'close_reason' => 'Fechamento fictício após conferência anual.',
                ])->save();
                foreach ([1, 2, 3] as $number) {
                    $session = AttendanceSession::query()->create([
                        'id' => (string) Str::uuid(), 'school_class_id' => $oldClass->id,
                        'attendance_date' => '2026-10-0'.$number,
                        'recorded_at' => '2026-10-0'.$number.' 09:00:00',
                        'recorded_by' => $this->auditFixtureUser->id,
                    ]);
                    AttendanceMark::query()->create([
                        'id' => (string) Str::uuid(), 'attendance_session_id' => $session->id,
                        'student_id' => $student->id,
                        'status' => $number === 3 ? 'absent' : 'present',
                    ]);
                }

                return [$student->id, $current->id, $past->id];
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

    private function login(string $role, array $schools = [self::ESCOLA_A1]): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();
    }

    private function url(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/alunos/'.$this->studentId.'/historico-escolar';
    }

    private function prior(int $year, array $extra = []): array
    {
        return array_replace([
            'year' => (string) $year,
            'stage' => '2º ano do Ensino Fundamental',
            'school' => 'Escola Externa Fictícia',
            'city' => 'Município Inventado',
            'state' => 'SP',
            'result' => 'Aprovado',
            'frequency' => '98,5',
            'grades' => [
                ['name' => 'Matemática', 'value' => 'A'],
                ['name' => 'Língua Portuguesa', 'value' => '8,5'],
            ],
        ], $extra);
    }

    private function issue(): array
    {
        $this->post($this->url().'/emitir', ['observations' => 'Dados confirmados pela direção fictícia.'])->assertRedirect();
        return $this->tenant(fn (): array => IssuedDocument::query()
            ->where('kind', 'school_transcript')->sole()->toArray());
    }

    public function test_anos_fechados_entram_automaticamente_abertos_nao_e_prioridade_para_rede(): void
    {
        $this->login('school_secretary');
        $this->post($this->url().'/anteriores', $this->prior(2025))->assertRedirect();
        $this->post($this->url().'/anteriores', $this->prior(2026))->assertRedirect();

        $page = $this->get($this->url())->assertOk()
            ->assertSee('Ano em curso:')->assertSee('2027')
            ->assertSee('já existe ano fechado pela rede')
            ->assertSee('2025')->assertSee('2026');

        $preview = $page->viewData('data');
        $this->assertSame([2025, 2026], array_column($preview['years'], 'year'));
        $this->assertSame([2027], $preview['inProgress']);
        $this->assertSame('anterior', $preview['years'][0]['source']);
        $this->assertSame('rede', $preview['years'][1]['source']);
        $this->assertSame('8,0', $preview['years'][1]['grades']['Matemática']);
        $this->assertSame(66.7, $preview['years'][1]['frequency']);
        $this->assertSame('aprovado', $preview['years'][1]['result']);
        $this->assertCount(2, $preview['previous']);
    }

    public function test_anos_anteriores_aceitam_conceito_editam_e_nao_duplicam_o_ano(): void
    {
        $this->login('school_director');
        $this->post($this->url().'/anteriores', $this->prior(2025))->assertRedirect();
        $this->post($this->url().'/anteriores', $this->prior(2025))
            ->assertSessionHasErrors('year');

        $id = $this->tenant(fn (): string => StudentPriorSchoolYear::query()->sole()->id);
        $this->post($this->url().'/anteriores', $this->prior(2025, [
            'record' => $id, 'school' => 'Escola Fictícia Corrigida',
            'grades' => [['name' => 'Ciências', 'value' => 'B']],
        ]))->assertRedirect();
        $record = $this->tenant(fn (): array => StudentPriorSchoolYear::query()->sole()->toArray());
        $this->assertSame('Escola Fictícia Corrigida', $record['school_name']);
        $this->assertSame(['Ciências' => 'B'], $record['component_grades']);
        $this->assertSame(1, $this->tenant(fn (): int => AuditLog::query()->where('action', 'school_transcript.prior.created')->count()));
        $audit = $this->tenant(fn () => AuditLog::query()->where('action', 'school_transcript.prior.updated')->sole()->after);
        $this->assertTrue($audit['grades_changed']);
        $this->assertStringNotContainsString('Ciências', json_encode($audit));
    }

    public function test_emissao_verificavel_tem_snapshot_imutavel_e_pagina_publica_sem_nome(): void
    {
        $this->login('school_secretary');
        $this->post($this->url().'/anteriores', $this->prior(2025))->assertRedirect();
        $issued = $this->issue();
        $snapshot = $issued['transcript_snapshot'];

        $this->assertSame('school_transcript', $issued['kind']);
        $this->assertSame([2025, 2026], array_column($snapshot['years'], 'year'));
        $this->assertSame([2027], $snapshot['in_progress']);
        $this->assertSame('Ana Fictícia da Silva', $snapshot['header']['student']);
        $this->assertSame('987654321-1/SP', $snapshot['header']['ra']);
        $this->assertSame(TranscriptFingerprint::fromSnapshot($snapshot), $issued['transcript_sha256']);
        $this->assertMatchesRegularExpression('/\A[A-HJ-NP-Z2-9]{10}\z/D', $issued['verification_code']);

        $this->tenant(function (): void {
            Student::query()->findOrFail($this->studentId)->update(['full_name' => 'Nome Atualizado Posteriormente']);
            StudentPriorSchoolYear::query()->sole()->update(['school_name' => 'Escola Nova Alterada']);
        });

        $this->get($this->url().'/documentos/'.$issued['id'])->assertOk()
            ->assertSee('Ana Fictícia da Silva')->assertSee('Escola Externa Fictícia')
            ->assertDontSee('Nome Atualizado Posteriormente')->assertDontSee('Escola Nova Alterada')
            ->assertSee($issued['verification_code'])->assertSee('<svg', false)
            ->assertSee('declaracao-imprimir.js');

        $this->tenant(function () use ($issued): void {
            try {
                DB::connection('tenant')->table('issued_documents')->where('id', $issued['id'])
                    ->update(['transcript_sha256' => str_repeat('a', 64)]);
                $this->fail('Snapshot e hash precisam permanecer imutáveis no PostgreSQL.');
            } catch (\Illuminate\Database\QueryException $exception) {
                $this->assertStringContainsString('Snapshot do histórico escolar', $exception->getMessage());
            }
        });

        auth()->logout();
        config()->set('tenancy.deployed_db_key', 'municipio_a');
        $this->get('/verificar/'.$issued['verification_code'])->assertOk()
            ->assertSee('Histórico escolar')->assertSee('Unidade A')->assertSee('Documento válido')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('Ana')->assertDontSee('Fictícia da Silva')
            ->assertDontSee('987654321')->assertDontSee('Matemática')
            ->assertDontSee('2026')->assertDontSee('2025')
            ->assertDontSee($issued['transcript_sha256']);
    }

    public function test_arquivamento_justificado_mantem_snapshot_e_auditoria_sem_motivo_em_claro(): void
    {
        $this->login('school_director');
        $this->post($this->url().'/anteriores', $this->prior(2025))->assertRedirect();
        $issued = $this->issue();
        $id = $this->tenant(fn (): string => StudentPriorSchoolYear::query()->sole()->id);

        $this->post($this->url().'/anteriores/'.$id.'/excluir', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post($this->url().'/anteriores/'.$id.'/excluir', [
            'reason' => 'Registro anterior lançado incorretamente para teste.',
        ])->assertRedirect();
        $record = $this->tenant(fn (): array => StudentPriorSchoolYear::withTrashed()->findOrFail($id)->toArray());
        $this->assertNotNull($record['deleted_at']);
        $this->assertSame('Registro anterior lançado incorretamente para teste.', $record['deletion_reason']);
        $this->get($this->url())->assertOk()->assertDontSee('Escola Externa Fictícia');
        $this->get($this->url().'/documentos/'.$issued['id'])->assertOk()->assertSee('Escola Externa Fictícia');
        $log = $this->tenant(fn () => AuditLog::query()->where('action', 'school_transcript.prior.archived')->sole()->after);
        $this->assertStringNotContainsString('Registro anterior lançado', json_encode($log));
        $this->assertSame(1, $this->tenant(fn (): int => AuditLog::query()->where('action', 'school_transcript.issued')->count()));
    }

    public function test_professor_nao_consulta_nem_emite_e_gestao_municipal_so_consulta(): void
    {
        $this->login('teacher');
        $this->get($this->url())->assertForbidden();
        $this->post($this->url().'/anteriores', $this->prior(2025))->assertForbidden();
        $this->post($this->url().'/emitir', [])->assertForbidden();

        $this->login('school_secretary', [self::ESCOLA_A2]);
        $this->get($this->url())->assertForbidden();
        $this->post($this->url().'/emitir', [])->assertForbidden();

        $this->login('municipal_manager', []);
        $this->get($this->url())->assertOk()->assertSee('A gestão municipal consulta');
        $this->post($this->url().'/emitir', [])->assertForbidden();
        $this->post($this->url().'/anteriores', $this->prior(2025))->assertForbidden();
    }

    public function test_revogacao_muda_validade_sem_mudar_snapshot_e_edicao_pede_2fa_recente(): void
    {
        $this->login('school_secretary');
        $issued = $this->issue();
        $this->travelTo(now()->addMinutes(20));
        $this->post($this->url().'/anteriores', $this->prior(2025))
            ->assertRedirect(route('two-factor.challenge'));
        $this->post($this->url().'/documentos/'.$issued['id'].'/revogar', [
            'reason' => 'Histórico substituído por documento retificado.',
        ])->assertRedirect(route('two-factor.challenge'));

        $this->assertSame(0, $this->tenant(fn (): int => StudentPriorSchoolYear::query()->count()));
        $this->assertNull($this->tenant(fn () => IssuedDocument::query()->findOrFail($issued['id'])->revoked_at));
    }
}
