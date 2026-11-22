<?php

namespace Tests\Feature\Security;

use App\Audit\AuditTrail;
use App\Models\Platform\Municipality;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class PostgresProductionRolesTest extends TestCase
{
    public function test_conta_web_real_nao_altera_auditoria_e_consegue_login_e_gravacao_auditada(): void
    {
        if (getenv('ASTUTI_PG_ROLE_INTEGRATION') !== '1') {
            $this->markTestSkipped('Exige as duas bases PostgreSQL 17 descartaveis e as duas contas reais do CI.');
        }

        $platformName = (string) getenv('PLATFORM_DB_DATABASE');
        $tenantName = (string) getenv('TENANT_DB_DATABASE');
        if (! preg_match('/\A[a-z][a-z0-9_]*_test\z/D', $platformName)
            || ! preg_match('/\A[a-z][a-z0-9_]*_test\z/D', $tenantName)
            || $platformName === $tenantName) {
            throw new RuntimeException('Teste de papeis recusou os bancos que nao sao descartaveis.');
        }

        $tenantMigration = array_replace(config('database.connections.pgsql'), [
            'driver' => 'pgsql',
            'url' => null,
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => $tenantName,
            'username' => 'astuti_migracao',
            'password' => (string) getenv('ASTUTI_PG_TEST_MIGRATION_PASSWORD'),
        ]);

        config()->set('database.connections.tenant', $tenantMigration);
        DB::purge('tenant');

        // As migrations executam autenticadas como dono, nunca como a conta web.
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'platform',
            '--path' => database_path('migrations/platform'),
            '--realpath' => true,
            '--force' => true,
        ]));
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]));

        $this->assertSame(0, Artisan::call('astuti:permissoes-pg', ['--confirmar' => 'PERMISSOES-PG']));
        $this->assertSame(0, Artisan::call('astuti:permissoes-pg', ['--confirmar' => 'PERMISSOES-PG']));

        $email = 'conta-web-'.strtolower(Str::random(12)).'@astuti.invalid';
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Teste ficticio de login com papel restrito',
            'email' => $email,
            'password' => 'SenhaForteFicticia2026!',
            'status' => 'active',
        ]);

        $appPlatform = $this->connectAsApplication($platformName);
        $appTenant = $this->connectAsApplication($tenantName);

        foreach ([[$appPlatform, 'platform_audit_logs'], [$appTenant, 'audit_logs']] as [$pdo, $table]) {
            $this->assertSame('astuti_aplicacao', $pdo->query('SELECT current_user')->fetchColumn());
            $this->assertDenied($pdo, "UPDATE public.{$table} SET action = 'alterada' WHERE false");
            $this->assertDenied($pdo, "DELETE FROM public.{$table} WHERE false");
            $this->assertDenied($pdo, "TRUNCATE TABLE public.{$table}");
            $this->assertDenied($pdo, "ALTER TABLE public.{$table} DISABLE TRIGGER ALL");
            $trigger = $table === 'audit_logs' ? 'audit_logs_block_mutation' : 'platform_audit_logs_block_mutation';
            $this->assertDenied($pdo, "DROP TRIGGER {$trigger} ON public.{$table}");
            $this->assertDenied($pdo, 'CREATE TABLE public.astuti_invasao_teste (id integer)');
            $this->assertDenied($pdo, "ALTER TABLE public.{$table} ADD COLUMN violacao integer");
            $this->assertSame(0, (int) $pdo->query("SELECT CASE WHEN has_table_privilege(current_user, 'public.{$table}', 'TRIGGER') THEN 1 ELSE 0 END")->fetchColumn());

            $owner = $pdo->query("SELECT pg_get_userbyid(relowner) FROM pg_class WHERE oid = 'public.{$table}'::regclass")->fetchColumn();
            $this->assertSame('astuti_migracao', $owner);
        }

        // Default privileges: nova tabela permite leitura e inclusao, mas
        // so recebe UPDATE apos uma reaplicacao explicita dos grants.
        $migrationPdo = $this->connectAsMigration($platformName);
        $migrationPdo->exec('CREATE TABLE IF NOT EXISTS public.astuti_role_future_test (id integer PRIMARY KEY)');
        try {
            $appPlatform->exec('INSERT INTO public.astuti_role_future_test (id) VALUES (1) ON CONFLICT DO NOTHING');
            $this->assertNotFalse($appPlatform->query('SELECT id FROM public.astuti_role_future_test'));
            $this->assertDenied($appPlatform, 'UPDATE public.astuti_role_future_test SET id = 2 WHERE false');

            $this->assertSame(0, Artisan::call('astuti:permissoes-pg', ['--confirmar' => 'PERMISSOES-PG']));
            $appPlatform->exec('UPDATE public.astuti_role_future_test SET id = 2 WHERE false');
        } finally {
            $migrationPdo->exec('DROP TABLE IF EXISTS public.astuti_role_future_test');
        }

        $platformSettings = config('database.connections.platform');
        config()->set('database.connections.platform', array_replace($platformSettings, [
            'username' => 'astuti_aplicacao',
            'password' => (string) getenv('ASTUTI_PG_TEST_APPLICATION_PASSWORD'),
        ]));
        DB::purge('platform');

        // O Laravel agora usa a mesma credencial web que os SQLs proibidos.
        $this->post('/login', [
            'email' => strtoupper($email),
            'password' => 'SenhaForteFicticia2026!',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, DB::connection('platform')->table('platform_audit_logs')
            ->where('action', 'auth.login')->where('user_id', $user->id)->count());

        $dbKey = 'municipio_papeis_'.strtolower(Str::random(8));
        $municipality = Municipality::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => 'municipio-ficticio-'.strtolower(Str::random(8)),
            'legal_name' => 'Municipio inteiramente ficticio',
            'db_key' => $dbKey,
            'status' => 'active',
        ]);

        $tenantApp = array_replace($tenantMigration, [
            'username' => 'astuti_aplicacao',
            'password' => (string) getenv('ASTUTI_PG_TEST_APPLICATION_PASSWORD'),
        ]);
        config()->set('tenancy.connections', [$dbKey => $tenantApp]);
        $context = app(TenantContext::class);
        $context->enter($municipality);

        try {
            app(AuditTrail::class)->record('tenant', 'roles.test.verified', userId: $user->id);
            $this->assertSame(1, DB::connection('tenant')->table('audit_logs')
                ->where('action', 'roles.test.verified')->where('user_id', $user->id)->count());
        } finally {
            $context->leave();
            config()->set('database.connections.platform', $platformSettings);
            DB::purge('platform');
        }
    }

    private function connectAsApplication(string $database): PDO
    {
        return $this->connect($database, 'astuti_aplicacao', (string) getenv('ASTUTI_PG_TEST_APPLICATION_PASSWORD'));
    }

    private function connectAsMigration(string $database): PDO
    {
        return $this->connect($database, 'astuti_migracao', (string) getenv('ASTUTI_PG_TEST_MIGRATION_PASSWORD'));
    }

    private function connect(string $database, string $user, string $password): PDO
    {
        return new PDO('pgsql:host=127.0.0.1;port=5432;dbname='.$database, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function assertDenied(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
            $this->fail('Operacao administrativa foi permitida para a conta web: '.$sql);
        } catch (PDOException $exception) {
            $this->assertSame('42501', $exception->getCode(), 'A recusa deve ser por falta de privilegio.');
        }
    }
}
