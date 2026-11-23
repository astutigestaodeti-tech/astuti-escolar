<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Models\Tenant\StudentExit;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Models\User;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentExitTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /** @var array<string, string> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = $this->inMunicipality(function (): array {
            $year = SchoolYear::query()->create(['id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active']);
            $make = function (string $name, string $school) use ($year): string {
                $student = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => $name, 'ra' => '000'.random_int(100000000, 999999999).'-1/SP']);
                StudentEnrollment::query()->create(['id' => (string) Str::uuid(), 'student_id' => $student->id, 'school_id' => $school, 'school_year_id' => $year->id, 'status' => 'active']);

                return $student->id;
            };

            $ids = [
                'aluno' => $make('Lívia Fictícia da Saída', self::ESCOLA_A1),
                'sem_autorizados' => $make('Otávio Fictício Sem Ninguém', self::ESCOLA_A1),
                'outra_escola' => $make('Lívia Fictícia de Outra Escola', self::ESCOLA_A2),
            ];
            $mae = Guardian::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Marta Fictícia Mãe', 'phone' => '11987654321']);
            StudentGuardian::query()->create(['id' => (string) Str::uuid(), 'student_id' => $ids['aluno'], 'guardian_id' => $mae->id, 'relationship' => 'mother', 'is_primary_contact' => true]);
            $ids['mae'] = $mae->id;
            $ids['vizinha'] = StudentPickupAuthorization::query()->create(['id' => (string) Str::uuid(), 'student_id' => $ids['aluno'], 'full_name' => 'Vera Fictícia Vizinha', 'relationship' => 'Vizinha', 'phone' => '11955554444', 'document' => 'RG 00.000.000-0'])->id;
            $ids['vencida'] = StudentPickupAuthorization::query()->create(['id' => (string) Str::uuid(), 'student_id' => $ids['aluno'], 'full_name' => 'Ivo Fictício Vencido', 'relationship' => 'Tio', 'phone' => '11944443333', 'expires_at' => today()->subDay()])->id;

            return $ids;
        });
    }

    private function inMunicipality(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->municipioA, $callback);
    }

    private function user(string $role, array $schools): User
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', ['code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp())])->assertRedirect();

        return $user;
    }

    private function url(string $school = self::ESCOLA_A1): string
    {
        return '/m/municipio-a/escolas/'.$school.'/saida';
    }

    public function test_professor_acha_o_aluno_sem_acento_e_ve_so_quem_pode_buscar_agora(): void
    {
        $this->user('teacher', [self::ESCOLA_A1]);

        $this->get($this->url())->assertOk()->assertSee('Nenhuma saída registrada hoje.');

        $this->get($this->url().'?busca=livia')
            ->assertOk()
            ->assertSee('Lívia Fictícia da Saída')
            ->assertDontSee('Lívia Fictícia de Outra Escola')
            ->assertSeeInOrder(['Marta Fictícia Mãe', 'Mãe', 'Entregar a Marta', 'Vera Fictícia Vizinha', 'Vizinha', 'Documento: RG 00.000.000-0', 'Entregar a Vera'])
            ->assertDontSee('Ivo Fictício Vencido')
            ->assertSee('(11) 98765-4321');

        // Busca pelo nome da mãe também acha o aluno.
        $this->get($this->url().'?busca='.urlencode('marta ficticia'))->assertOk()->assertSee('Lívia Fictícia da Saída');

        $this->get($this->url().'?busca=otavio')
            ->assertOk()
            ->assertSee('Nenhuma pessoa autorizada cadastrada.');
    }

    public function test_entrega_registra_saida_com_auditoria_e_aparece_nas_saidas_de_hoje(): void
    {
        $user = $this->user('teacher', [self::ESCOLA_A1]);

        $this->post($this->url().'/'.$this->ids['aluno'], ['pessoa' => 'authorization:'.$this->ids['vizinha']])
            ->assertRedirect($this->url())
            ->assertSessionHas('status', fn (string $message): bool => str_starts_with($message, 'Saída registrada: Lívia Fictícia da Saída entregue a Vera Fictícia Vizinha (Vizinha) às '));

        $exit = $this->inMunicipality(fn (): array => StudentExit::query()->sole()->only(['student_id', 'school_id', 'person_type', 'person_name', 'person_relationship', 'recorded_by']));
        $this->assertSame([
            'student_id' => $this->ids['aluno'], 'school_id' => self::ESCOLA_A1, 'person_type' => 'authorization',
            'person_name' => 'Vera Fictícia Vizinha', 'person_relationship' => 'Vizinha', 'recorded_by' => $user->id,
        ], $exit);

        $after = $this->inMunicipality(fn (): array => AuditLog::query()->where('action', 'student_exit.recorded')->sole()->after);
        $this->assertSame('Vera Fictícia Vizinha', $after['person_name']);
        $this->assertArrayNotHasKey('phone', $after);

        $this->get($this->url())->assertOk()->assertSeeInOrder(['Saídas de hoje', 'Lívia Fictícia da Saída', 'Vera Fictícia Vizinha']);
        $this->get($this->url().'?busca=livia')->assertSee('Já saiu hoje às');
    }

    public function test_pessoa_vencida_ou_de_outro_aluno_nao_pode_receber(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);

        $this->post($this->url().'/'.$this->ids['aluno'], ['pessoa' => 'authorization:'.$this->ids['vencida']])
            ->assertSessionHasErrors(['pessoa' => 'Esta pessoa não está autorizada a buscar o aluno. Não entregue e procure a secretaria.']);
        $this->post($this->url().'/'.$this->ids['sem_autorizados'], ['pessoa' => 'guardian:'.$this->ids['mae']])
            ->assertSessionHasErrors('pessoa');
        $this->post($this->url().'/'.$this->ids['aluno'], ['pessoa' => 'qualquer coisa'])
            ->assertSessionHasErrors('pessoa');

        $this->assertSame(0, $this->inMunicipality(fn (): int => StudentExit::query()->count()));
    }

    private function recordExit(): string
    {
        $this->post($this->url().'/'.$this->ids['aluno'], ['pessoa' => 'guardian:'.$this->ids['mae']])->assertRedirect($this->url());

        return $this->inMunicipality(fn (): string => StudentExit::query()->latest('exited_at')->firstOrFail()->id);
    }

    private function undoUrl(string $exit): string
    {
        return $this->url().'/registro/'.$exit.'/desfazer';
    }

    public function test_quem_registrou_desfaz_em_ate_10_minutos_com_motivo_e_auditoria(): void
    {
        $this->travelTo(today()->setTime(15, 0));
        $user = $this->user('teacher', [self::ESCOLA_A1]);
        $exit = $this->recordExit();

        $this->get($this->url())->assertOk()->assertSee('Desfazer saída')->assertSee('1 saída nesta escola');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->ids['aluno'])->assertOk()
            ->assertSee('Última saída:')->assertSee('entregue a Marta Fictícia Mãe (Mãe)');

        $this->post($this->undoUrl($exit), ['motivo' => 'curto'])->assertSessionHasErrors(['motivo' => 'Escreva o motivo com pelo menos 10 caracteres.']);

        $this->post($this->undoUrl($exit), ['motivo' => 'Cliquei na aluna errada, ela ainda está na sala.'])
            ->assertRedirect($this->url())
            ->assertSessionHas('status', fn (string $message): bool => str_starts_with($message, 'Saída desfeita: Lívia Fictícia da Saída volta a constar como na escola.'));

        $saved = $this->inMunicipality(fn (): array => StudentExit::query()->findOrFail($exit)->only(['cancelled_by', 'cancel_reason']));
        $this->assertSame(['cancelled_by' => $user->id, 'cancel_reason' => 'Cliquei na aluna errada, ela ainda está na sala.'], $saved);

        $after = $this->inMunicipality(fn (): array => AuditLog::query()->where('action', 'student_exit.cancelled')->sole()->after);
        $this->assertSame('[REDACTED]', $after['cancel_reason']);
        $this->assertSame($user->id, $after['cancelled_by']);

        // Fica visível como desfeita, não conta como saída e não avisa "já saiu".
        $this->get($this->url())->assertOk()->assertSee('Desfeita às')->assertSee('0 saídas nesta escola')->assertDontSee('Desfazer saída');
        $this->get($this->url().'?busca=livia')->assertDontSee('Já saiu hoje às');
        $this->get('/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos/'.$this->ids['aluno'])->assertDontSee('Última saída:');

        $this->post($this->undoUrl($exit), ['motivo' => 'Tentando desfazer de novo.'])->assertSessionHasErrors(['motivo' => 'Esta saída já foi desfeita.']);
        $this->assertSame(1, $this->inMunicipality(fn (): int => AuditLog::query()->where('action', 'student_exit.cancelled')->count()));
    }

    public function test_depois_de_10_minutos_so_a_gestao_desfaz_e_outro_professor_nao(): void
    {
        $this->travelTo(today()->setTime(15, 0));
        $this->user('teacher', [self::ESCOLA_A1]);
        $exit = $this->recordExit();
        $this->inMunicipality(fn () => StudentExit::query()->whereKey($exit)->update(['exited_at' => now()->subMinutes(11)]));

        $this->get($this->url())->assertDontSee('Desfazer saída');
        $this->post($this->undoUrl($exit), ['motivo' => 'Registrei a saída errada.'])
            ->assertSessionHasErrors(['motivo' => 'Passou o prazo de 10 minutos para desfazer. Peça à secretaria ou à direção.']);

        $this->user('teacher', [self::ESCOLA_A1]);
        $this->post($this->undoUrl($exit), ['motivo' => 'Registro de outra pessoa.'])->assertSessionHasErrors('motivo');

        $this->user('school_secretary', [self::ESCOLA_A1]);
        $this->get($this->url())->assertSee('Desfazer saída');
        $this->post($this->undoUrl($exit), ['motivo' => 'A família ligou: a aluna não foi retirada.'])->assertSessionHas('status');
        $this->assertNotNull($this->inMunicipality(fn () => StudentExit::query()->findOrFail($exit)->cancelled_at));
    }

    public function test_saida_de_outra_escola_nao_pode_ser_desfeita(): void
    {
        $this->user('school_secretary', [self::ESCOLA_A1]);
        $exit = $this->recordExit();

        $this->user('school_secretary', [self::ESCOLA_A2]);
        $this->post($this->undoUrl($exit), ['motivo' => 'Tentativa de outra escola.'])->assertForbidden();
        $this->post('/m/municipio-a/escolas/'.self::ESCOLA_A2.'/saida/registro/'.$exit.'/desfazer', ['motivo' => 'Tentativa de outra escola.'])->assertNotFound();
        $this->assertNull($this->inMunicipality(fn () => StudentExit::query()->findOrFail($exit)->cancelled_at));
    }

    public function test_outra_escola_nao_alcanca_o_aluno(): void
    {
        $this->user('teacher', [self::ESCOLA_A2]);

        $this->get($this->url())->assertForbidden();
        $this->get($this->url(self::ESCOLA_A2).'?busca=livia')->assertOk()->assertDontSee('Lívia Fictícia da Saída');
        $this->post($this->url(self::ESCOLA_A2).'/'.$this->ids['aluno'], ['pessoa' => 'guardian:'.$this->ids['mae']])->assertNotFound();
    }
}
