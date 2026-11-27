<?php

namespace Tests\Feature\Students;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;

class StudentIdentityWriteTest extends TwoMunicipalityTestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_documentos_com_zeros_e_conflitos_na_edicao_preservam_cadastro_e_privacidade(): void
    {
        $yearId = app(TenantContext::class)->runFor($this->municipioA, function (): string {
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2027, 'start_date' => '2027-02-01', 'end_date' => '2027-12-18', 'status' => 'active',
            ]);
            $other = Student::query()->create([
                'id' => (string) Str::uuid(), 'full_name' => 'Pessoa Fictícia Reservada', 'birth_date' => '2019-04-01',
                'cpf' => '123.456.789-09', 'ra' => '000987654321-1/SP',
            ]);
            StudentEnrollment::query()->create([
                'id' => (string) Str::uuid(), 'student_id' => $other->id, 'school_id' => self::ESCOLA_A2,
                'school_year_id' => $year->id, 'status' => 'active',
            ]);

            return $year->id;
        });
        $user = $this->criarUsuario('identidade-regressao@example.invalid');
        $this->vincular($user, $this->municipioA, 'school_secretary', [self::ESCOLA_A1]);
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at(self::SECRET, now()->getTimestamp()),
        ])->assertRedirect();

        $url = '/m/municipio-a/escolas/'.self::ESCOLA_A1.'/alunos';
        $data = [
            'full_name' => 'Criança Fictícia Identidade', 'birth_date' => '2019-05-10', 'school_year_id' => $yearId,
            'started_at' => '2027-02-03', 'cpf' => '01234567890', 'ra' => '000123456789-0/sp',
        ];
        $this->post($url, $data)->assertRedirect();
        [$id, $version, $before, $auditCount] = app(TenantContext::class)->runFor($this->municipioA, function (): array {
            $student = Student::query()->where('full_name', 'Criança Fictícia Identidade')->sole();
            $this->assertSame('012.345.678-90', $student->cpf);
            $this->assertSame('000123456789-0/SP', $student->ra);

            return [$student->id, DB::connection('tenant')->table('students')->where('id', $student->id)->selectRaw('xmin::text AS token')->first()->token,
                $student->getAttributes(), AuditLog::query()->whereIn('auditable_type', [Student::class, StudentEnrollment::class])->count()];
        });

        $payload = ['full_name' => 'Alteração Fictícia Não Permitida', 'birth_date' => '2019-05-10', 'versao' => $version];
        foreach ([
            ['cpf' => '12345678909', 'message' => 'Este CPF já está no cadastro de outro aluno da rede. Confira o documento.'],
            ['ra' => '0009876543211/sp', 'message' => 'Este RA já está cadastrado. Confira o registro existente antes de prosseguir.'],
        ] as $case) {
            $message = $case['message'];
            unset($case['message']);
            $field = array_key_first($case);
            $this->from($url.'/'.$id.'/editar')->put($url.'/'.$id, [...$payload, ...$case])
                ->assertSessionHasErrors([$field => $message]);
            $this->get($url.'/'.$id.'/editar')->assertOk()->assertDontSee('Pessoa Fictícia Reservada');
            $this->post($url, array_replace($data, ['full_name' => 'Novo Cadastro Fictício'], $case, $field === 'cpf' ? ['ra' => null] : ['cpf' => null]))
                ->assertSessionHasErrors([$field => $message]);
        }
        $this->put($url.'/'.$id, [...$payload, 'cpf' => '00000000000', 'ra' => '123'])->assertSessionHasErrors(['cpf', 'ra']);
        app(TenantContext::class)->runFor($this->municipioA, function () use ($id, $before, $auditCount): void {
            $this->assertSame($before, Student::query()->findOrFail($id)->getAttributes());
            $this->assertSame(2, Student::query()->count());
            $this->assertSame(2, StudentEnrollment::query()->count());
            $this->assertSame($auditCount, AuditLog::query()->whereIn('auditable_type', [Student::class, StudentEnrollment::class])->count());
        });

        $this->put($url.'/'.$id, ['full_name' => 'Criança Fictícia Identidade', 'birth_date' => '2019-05-10', 'versao' => $version, 'cpf' => '', 'ra' => ''])
            ->assertRedirect($url.'/'.$id);
        app(TenantContext::class)->runFor($this->municipioA, function () use ($id): void {
            $student = Student::query()->findOrFail($id);
            $this->assertNull($student->cpf);
            $this->assertNull($student->ra);
        });
    }
}
