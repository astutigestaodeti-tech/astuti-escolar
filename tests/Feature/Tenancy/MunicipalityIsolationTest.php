<?php

namespace Tests\Feature\Tenancy;

use App\Audit\AuditActor;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class MunicipalityIsolationTest extends TestCase
{
    private TenantContext $context;

    private Municipality $municipioA;

    private Municipality $municipioB;

    private string $fixtureAuditUserId;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('TENANCY_INTEGRATION') !== '1') {
            $this->markTestSkipped('Os testes municipais requerem duas bases MariaDB exclusivas de teste.');
        }

        $platform = (string) getenv('PLATFORM_DB_DATABASE');
        $dbA = (string) getenv('TENANCY_TEST_DB_A');
        $dbB = (string) getenv('TENANCY_TEST_DB_B');

        foreach ([$platform, $dbA, $dbB] as $databaseName) {
            if (! preg_match('/\A[a-z0-9_]+_test\z/D', $databaseName)) {
                throw new RuntimeException('Somente bancos MariaDB terminados em _test podem ser usados.');
            }
        }

        if (count(array_unique([$platform, $dbA, $dbB])) !== 3) {
            throw new RuntimeException('Os tres bancos de teste devem ser diferentes.');
        }

        $base = config('database.connections.mariadb');
        $host = (string) getenv('TENANCY_TEST_DB_HOST');
        $port = (string) getenv('TENANCY_TEST_DB_PORT');
        $username = (string) getenv('TENANCY_TEST_DB_USER');
        $password = (string) getenv('TENANCY_TEST_DB_PASSWORD');

        config()->set('tenancy.connections', [
            'municipio_a' => array_replace($base, compact('host', 'port', 'username', 'password'), ['database' => $dbA, 'unix_socket' => '', 'url' => null]),
            'municipio_b' => array_replace($base, compact('host', 'port', 'username', 'password'), ['database' => $dbB, 'unix_socket' => '', 'url' => null]),
        ]);

        $this->context = app(TenantContext::class);
        $this->context->leave();

        Artisan::call('migrate', [
            '--database' => 'platform',
            '--path' => database_path('migrations/platform'),
            '--realpath' => true,
            '--force' => true,
        ]);

        // A construção do cenário de escolas é uma gravação auditada,
        // inclusive nos testes de isolamento que não simulam sessão web.
        $this->fixtureAuditUserId = User::query()->firstOrCreate(
            ['email' => 'ator-isolamento@astuti.invalid'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Responsável de isolamento sintético',
                'password' => Hash::make(Str::random(40)),
                'status' => 'active',
            ],
        )->id;

        // A base de integracao e reutilizada pela segunda rodada de testes.
        // Limpar apenas os registros de provisao descartaveis.
        DB::connection('platform')->table('municipality_provision_attempts')->delete();

        $this->municipioA = $this->municipio('municipio-a', 'municipio_a');
        $this->municipioB = $this->municipio('municipio-b', 'municipio_b');
    }

    protected function tearDown(): void
    {
        if (isset($this->context)) {
            $this->context->leave();
        }

        parent::tearDown();
    }

    public function test_duas_bases_nao_compartilham_escolas(): void
    {
        $this->prepararEscolas();

        $this->context->enter($this->municipioA);
        $this->assertSame(['Escola Central', 'Unidade A'], School::orderBy('name')->pluck('name')->all());
        $this->assertSame('10000000-0000-0000-0000-000000000001', School::where('name', 'Escola Central')->value('id'));
        $this->context->leave();

        $this->context->enter($this->municipioB);
        $this->assertSame(['Escola Central', 'Unidade B'], School::orderBy('name')->pluck('name')->all());
        $this->assertSame('20000000-0000-0000-0000-000000000002', School::where('name', 'Escola Central')->value('id'));
    }

    public function test_contexto_e_limpo_na_sequencia_a_b_a_no_mesmo_processo(): void
    {
        $this->prepararEscolas();

        foreach ([$this->municipioA, $this->municipioB, $this->municipioA] as $indice => $municipio) {
            $esperado = $indice === 1 ? 'Unidade B' : 'Unidade A';
            $this->assertSame($esperado, $this->context->runFor(
                $municipio,
                fn () => School::where('name', 'like', 'Unidade %')->sole()->name,
            ));
            $this->assertFalse($this->context->hasActive());
        }
    }

    public function test_consulta_sem_contexto_municipal_e_bloqueada(): void
    {
        $this->context->leave();
        $this->expectException(LogicException::class);

        School::query()->count();
    }

    public function test_municipio_desconhecido_nao_abre_conexao(): void
    {
        $desconhecido = $this->municipio('desconhecido', 'banco_nao_configurado');

        try {
            $this->context->enter($desconhecido);
            $this->fail('O banco desconhecido nao pode ser ativado.');
        } catch (InvalidArgumentException $exception) {
            $this->assertFalse($this->context->hasActive());
        }

        $this->expectException(LogicException::class);
        School::query()->count();
    }

    public function test_dois_municipios_nao_podem_ter_a_mesma_base_configurada(): void
    {
        $connections = config('tenancy.connections');
        $connections['municipio_b']['database'] = $connections['municipio_a']['database'];
        config()->set('tenancy.connections', $connections);

        $this->expectException(InvalidArgumentException::class);
        $this->context->enter($this->municipioB);
    }

    public function test_base_municipal_nao_pode_apontar_para_o_banco_central(): void
    {
        config()->set('tenancy.connections.municipio_a.database', (string) getenv('PLATFORM_DB_DATABASE'));

        $this->expectException(InvalidArgumentException::class);
        $this->context->enter($this->municipioA);
    }

    public function test_configuracao_municipal_nao_aceita_url_que_sobrescreva_destino(): void
    {
        config()->set('tenancy.connections.municipio_a.url', 'mariadb://usuario:senha@127.0.0.1/qualquer_banco');

        $this->expectException(InvalidArgumentException::class);
        $this->context->enter($this->municipioA);
    }

    public function test_db_key_central_nao_pode_ser_reutilizado_por_outro_municipio(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        Municipality::query()->create([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'slug' => 'municipio-duplicado',
            'legal_name' => 'Municipio duplicado ficticio',
            'db_key' => 'municipio_a',
            'status' => 'active',
        ]);
    }

    public function test_conexao_central_nao_muda_ao_trocar_municipio(): void
    {
        $this->prepararEscolas();
        $esperado = (string) getenv('PLATFORM_DB_DATABASE');
        $this->assertSame($esperado, DB::connection('platform')->getDatabaseName());

        $this->context->runFor($this->municipioA, fn () => $this->assertSame($esperado, DB::connection('platform')->getDatabaseName()));
        $this->context->runFor($this->municipioB, fn () => $this->assertSame($esperado, DB::connection('platform')->getDatabaseName()));
        $this->assertSame($esperado, DB::connection('platform')->getDatabaseName());
    }

    public function test_contexto_e_removido_mesmo_se_ocorrer_excecao(): void
    {
        try {
            $this->context->runFor($this->municipioA, function (): void {
                throw new RuntimeException('Falha simulada.');
            });
            $this->fail('A excecao de teste deveria ser propagada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha simulada.', $exception->getMessage());
        }

        $this->assertFalse($this->context->hasActive());
        $this->expectException(LogicException::class);
        School::query()->count();
    }

    public function test_municipio_inativo_nao_ativa_contexto(): void
    {
        $municipio = $this->municipioA;
        $municipio->update(['status' => 'failed']);
        $this->expectException(LogicException::class);

        $this->context->enter($municipio);
    }

    public function test_provisao_e_idempotente_e_ativa_municipio(): void
    {
        $municipio = $this->municipioA;
        $municipio->update(['status' => 'provisioning']);

        $this->assertSame(0, Artisan::call('astuti:municipio-provisionar', ['slug' => $municipio->slug]));
        $this->assertSame('active', $municipio->fresh()->status);
        $this->assertSame(0, Artisan::call('astuti:municipio-provisionar', ['slug' => $municipio->slug]));
        $this->assertSame('active', $municipio->fresh()->status);
        $this->assertSame(
            ['completed', 'completed'],
            DB::connection('platform')->table('municipality_provision_attempts')
                ->where('municipality_id', $municipio->id)->orderBy('id')->pluck('status')->all(),
        );
    }

    public function test_provisao_falha_sem_abrir_banco_nao_configurado(): void
    {
        $municipio = $this->municipio('municipio-falha', 'outro_banco_nao_configurado', 'provisioning');

        $this->assertSame(1, Artisan::call('astuti:municipio-provisionar', ['slug' => $municipio->slug]));
        $this->assertSame('failed', $municipio->fresh()->status);
        $this->assertFalse($this->context->hasActive());
        $this->assertSame(
            ['failed'],
            DB::connection('platform')->table('municipality_provision_attempts')
                ->where('municipality_id', $municipio->id)->orderBy('id')->pluck('status')->all(),
        );
    }

    public function test_tentativas_de_provisao_de_outros_testes_nao_permanecem_na_base(): void
    {
        $this->assertSame(
            0,
            DB::connection('platform')->table('municipality_provision_attempts')->count(),
        );
    }

    private function municipio(string $slug, string $key, string $status = 'active'): Municipality
    {
        return Municipality::query()->updateOrCreate(
            ['slug' => $slug],
            ['id' => match ($slug) {
                'municipio-a' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'municipio-b' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                default => 'cccccccc-cccc-4ccc-8ccc-'.substr(hash('sha256', $slug), 0, 12),
            }, 'legal_name' => 'Municipio ficticio '.$slug, 'db_key' => $key, 'status' => $status],
        );
    }

    private function prepararEscolas(): void
    {
        foreach ([
            [$this->municipioA, '10000000-0000-0000-0000-000000000001', 'Unidade A'],
            [$this->municipioB, '20000000-0000-0000-0000-000000000002', 'Unidade B'],
        ] as [$municipio, $id, $name]) {
            $this->context->enter($municipio);
            try {
                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);
                foreach (['student_enrollments', 'students'] as $table) {
                    if (Schema::connection('tenant')->hasTable($table)) {
                        DB::connection('tenant')->table($table)->delete();
                    }
                }
                DB::connection('tenant')->table('schools')->delete();
                app(AuditActor::class)->runAs($this->fixtureAuditUserId, function () use ($id, $name): void {
                    School::create(['id' => $id, 'name' => 'Escola Central']);
                    School::create(['id' => substr_replace($id, '3', 0, 1), 'name' => $name]);
                });
            } finally {
                $this->context->leave();
            }
        }
    }
}
