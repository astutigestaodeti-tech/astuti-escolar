<?php

namespace Tests\Feature\Census;

use App\Educacenso\CodigoCenso;
use App\Educacenso\Leiaute;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\InepIdentifier;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolClassCensus;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\StaffCensus;
use App\Models\Tenant\StaffMember;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\Totp;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\TwoMunicipalityTestCase;
use ZipArchive;

class EducacensoReturnTest extends TwoMunicipalityTestCase
{
    private const URL = '/m/municipio-a/conferencia-do-censo/retorno';

    /** @var array<string, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-07-10 10:00:00'));
        $this->ids = $this->tenant(function (): array {
            School::query()->findOrFail(self::ESCOLA_A1)->forceFill(['inep_code' => '35999001'])->save();
            $year = SchoolYear::query()->create([
                'id' => (string) Str::uuid(), 'year' => 2026, 'start_date' => '2026-02-01', 'end_date' => '2026-12-15', 'status' => 'active',
            ]);
            $class = SchoolClass::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'school_year_id' => $year->id,
                'name' => '3º Ano A', 'stage' => 'Ensino Fundamental', 'modality' => 'Regular', 'shift' => 'Manhã', 'status' => 'active',
            ]);
            $census = SchoolClassCensus::query()->create(['school_class_id' => $class->id]);
            $ana = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Ana Fictícia de Souza', 'birth_date' => '2018-03-01']);
            $bia = Student::query()->create(['id' => (string) Str::uuid(), 'full_name' => 'Bia Fictícia Lima', 'birth_date' => '2018-07-20']);
            $matriculas = [];
            foreach ([$ana, $bia] as $aluno) {
                $matriculas[] = StudentEnrollment::query()->create([
                    'id' => (string) Str::uuid(), 'student_id' => $aluno->id, 'school_id' => self::ESCOLA_A1,
                    'school_year_id' => $year->id, 'school_class_id' => $class->id, 'status' => 'active', 'started_at' => '2026-02-01',
                ])->id;
            }
            $professora = StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => self::ESCOLA_A1, 'full_name' => 'Helena Fictícia Prado', 'role' => 'teacher', 'status' => 'active',
            ]);
            StaffCensus::query()->create(['staff_member_id' => $professora->id, 'birth_date' => '1985-04-10', 'filiation' => 0]);

            return ['class' => $class->id, 'code' => $census->census_code, 'ana' => $ana->id, 'bia' => $bia->id,
                'm_ana' => $matriculas[0], 'm_bia' => $matriculas[1], 'staff' => $professora->id];
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

    private function login(string $role, array $schools = []): void
    {
        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, $role, $schools);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->post('/seguranca/segundo-fator/desafio', [
            'code' => app(Totp::class)->at($secret, now()->getTimestamp()),
        ])->assertRedirect();
    }

    /** Linha no leiaute 2026, com os campos informados e o resto vazio. */
    private function linha(string $registro, array $campos): string
    {
        $valores = array_fill(1, count(Leiaute::do(2026)->campos($registro)), '');
        $valores[1] = $registro;

        return implode('|', array_replace($valores, $campos));
    }

    private function arquivo(array $override = []): string
    {
        $ana = CodigoCenso::dePessoa($this->ids['ana']);
        $bia = CodigoCenso::dePessoa($this->ids['bia']);
        $professora = CodigoCenso::dePessoa($this->ids['staff']);
        $linhas = [
            $this->linha('00', [2 => '35999001']),
            $this->linha('10', [2 => '35999001']),
            $this->linha('20', [2 => '35999001', 3 => $this->ids['code'], 4 => '1234567']),
            $this->linha('30', [2 => '35999001', 3 => $ana, 4 => '111111111111', 6 => 'ANA FICTICIA DE SOUZA', 7 => '01/03/2018']),
            $this->linha('30', [2 => '35999001', 3 => $bia, 4 => '222222222222', 6 => $override['bia_nome'] ?? 'BIA FICTICIA LIMA', 7 => '20/07/2018']),
            $this->linha('30', [2 => '35999001', 3 => $professora, 4 => '333333333333', 6 => 'HELENA FICTICIA PRADO', 7 => '10/04/1985']),
            $this->linha('30', [2 => '35999001', 3 => 'ZZZZZZZZZZZZZZZZZZZZ', 4 => '444444444444', 6 => 'PESSOA DESCONHECIDA', 7 => '01/01/2000']),
            $this->linha('50', [2 => '35999001', 3 => $professora, 5 => $this->ids['code']]),
            $this->linha('60', [2 => '35999001', 3 => $ana, 5 => $this->ids['code'], 6 => '1234567', 7 => '900000001']),
            $this->linha('60', [2 => '35999001', 3 => $bia, 5 => $this->ids['code'], 6 => '1234567', 7 => '900000002']),
            $this->linha('00', [2 => '35000000']),
            '99|',
        ];

        return mb_convert_encoding(implode("\r\n", $linhas)."\r\n", 'ISO-8859-1', 'UTF-8');
    }

    private function enviar(string $conteudo, string $nome = 'exportacao.txt')
    {
        return $this->post(self::URL.'/conferir', ['arquivo' => UploadedFile::fake()->createWithContent($nome, $conteudo)]);
    }

    public function test_gestao_confere_e_grava_os_codigos_que_conferem_com_auditoria_sem_nomes(): void
    {
        $this->login('municipal_manager');
        $this->get(self::URL)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Arquivo de exportação');
        $this->get('/m/municipio-a/conferencia-do-censo')->assertOk()->assertSee(self::URL, false);

        $previa = $this->enviar($this->arquivo(['bia_nome' => 'BEATRIZ FICTICIA LIMA']));
        if ($dump = getenv('ASTUTI_DUMP_TELAS') ?: null) {
            $html = str_replace(rtrim((string) config('app.url'), '/').'/', 'file://'.public_path().'/', $previa->getContent());
            file_put_contents(rtrim($dump, '/').'/retorno_educacenso_conferir.html', preg_replace('/\.(css|js)\?v=\d+/', '.$1', $html));
        }
        $previa->assertOk()
            ->assertSee('1 escola(s) da rede no arquivo')
            ->assertSee('escola(s) de fora da rede foram ignoradas')
            ->assertSee('O nome no arquivo é diferente do cadastro.')
            ->assertSee('O aluno desta matrícula não conferiu com o cadastro.')
            ->assertSee('Pessoa PESSOA DESCONHECIDA')
            ->assertSee('Gravar 4 código(s)');
        $this->assertSame(0, $this->tenant(fn () => InepIdentifier::query()->count()));

        $token = session('educacenso_retorno')['token'];
        $this->post(self::URL, ['token' => $token])->assertRedirect(self::URL)->assertSessionHas('status');

        $gravados = $this->tenant(fn () => InepIdentifier::query()->pluck('inep_code', 'entity_id')->all());
        $this->assertSame([
            $this->ids['class'] => '1234567', $this->ids['ana'] => '111111111111', $this->ids['staff'] => '333333333333', $this->ids['m_ana'] => '900000001',
        ], array_intersect_key($gravados, array_flip([$this->ids['class'], $this->ids['ana'], $this->ids['staff'], $this->ids['m_ana']])));
        $this->assertCount(4, $gravados);
        $this->assertArrayNotHasKey($this->ids['bia'], $gravados);
        // A matrícula da Bia existe, mas sem a pessoa conferida não é vinculada.
        $this->assertArrayNotHasKey($this->ids['m_bia'], $gravados);

        $log = $this->tenant(fn () => AuditLog::query()->where('action', 'educacenso_return.imported')->sole());
        $this->assertSame(4, $log->after['linked']);
        $this->assertSame(hash('sha256', $this->arquivo(['bia_nome' => 'BEATRIZ FICTICIA LIMA'])), $log->after['file_sha256']);
        $this->assertStringNotContainsString('FICTICIA', json_encode($log->after));
        $this->assertStringNotContainsString('111111111111', json_encode($log->after));

        // Token usado não serve de novo.
        $this->post(self::URL, ['token' => $token])->assertSessionHasErrors(['arquivo']);

        // Reenvio corrigido: os já gravados aparecem como iguais; só a Bia e a matrícula dela entram.
        $this->enviar($this->arquivo())->assertOk()->assertSee('Gravar 2 código(s)');
    }

    public function test_codigo_diferente_ja_gravado_e_conflito_e_nunca_e_trocado(): void
    {
        $this->tenant(fn () => InepIdentifier::query()->create([
            'id' => (string) Str::uuid(), 'kind' => InepIdentifier::STUDENT, 'entity_id' => $this->ids['ana'],
            'inep_code' => '999999999999', 'census_year' => 2025, 'imported_by' => (string) Str::uuid(),
        ]));
        $this->login('municipal_manager');

        $this->enviar($this->arquivo())->assertOk()->assertSee('Já tem o código 999999999999 gravado.');
        $this->post(self::URL, ['token' => session('educacenso_retorno')['token']])->assertRedirect();

        $this->assertSame('999999999999', $this->tenant(fn () => InepIdentifier::query()->where('entity_id', $this->ids['ana'])->value('inep_code')));
    }

    public function test_arquivo_fora_do_leiaute_nao_gera_plano_e_zip_com_txt_e_aceito(): void
    {
        $this->login('municipal_manager');

        $this->enviar("00|35999001|X\r\n99|")->assertOk()
            ->assertSee('O arquivo não está no leiaute do Educacenso 2026')
            ->assertSee('o leiaute 2026 pede 53')
            ->assertDontSee('Gravar ');
        $this->assertSame([], session('educacenso_retorno')['plano']);

        $this->post(self::URL.'/conferir', ['arquivo' => UploadedFile::fake()->createWithContent('lista.csv', 'a;b')])
            ->assertSessionHasErrors(['arquivo']);

        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Extensão zip indisponível.');
        }
        $caminho = tempnam(sys_get_temp_dir(), 'ret').'.zip';
        $zip = new ZipArchive;
        $zip->open($caminho, ZipArchive::CREATE);
        $zip->addFromString('EXPORTACAO_2026.txt', $this->arquivo());
        $zip->close();
        $this->enviar((string) file_get_contents($caminho), 'exportacao.zip')->assertOk()->assertSee('Gravar 6 código(s)');
        @unlink($caminho);
    }

    public function test_escola_e_sem_segundo_fator_recente_nao_acessam(): void
    {
        $this->login('school_director', [self::ESCOLA_A1]);
        $this->get(self::URL)->assertForbidden();
        $this->enviar($this->arquivo())->assertForbidden();

        $user = $this->criarUsuario((string) Str::uuid().'@example.invalid');
        $this->vincular($user, $this->municipioA, 'municipal_manager');
        $this->actingAs($user);
        $this->enviar($this->arquivo())->assertForbidden();
        $this->assertSame(0, $this->tenant(fn () => InepIdentifier::query()->count()));
    }
}
