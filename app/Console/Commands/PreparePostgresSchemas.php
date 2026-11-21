<?php

namespace App\Console\Commands;

use App\Models\Platform\Municipality;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PreparePostgresSchemas extends Command
{
    protected $signature = 'astuti:preparar-pg-vazio';

    protected $description = 'Cria apenas as tabelas PostgreSQL locais ficticias, sem inserir usuarios ou alunos.';

    public function handle(TenantConnectionManager $manager): int
    {
        $platform = config('database.connections.platform', []);
        $tenant = config('tenancy.connections.monte_sereno', []);

        if (! app()->environment('local')
            || ! $this->safeTarget($platform, 'astuti_platform_dev')
            || ! $this->safeTarget($tenant, 'astuti_monte_sereno_dev')) {
            $this->error('Comando exclusivo das bases PostgreSQL ficticias do desenvolvimento local.');

            return self::FAILURE;
        }

        try {
            if (Schema::connection('platform')->hasTable('users')
                && DB::connection('platform')->table('users')->exists()) {
                $this->error('Banco central ja possui usuarios. Preparacao recusada.');

                return self::FAILURE;
            }

            if (Artisan::call('migrate', [
                '--database' => 'platform',
                '--path' => 'database/migrations/platform',
                '--force' => true,
            ]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            $manager->activate(new Municipality(['db_key' => 'monte_sereno']));

            if (Schema::connection('tenant')->hasTable('students')
                && (int) DB::connection('tenant')->selectOne('SELECT COUNT(*) AS total FROM students')->total > 0) {
                $this->error('Banco municipal ja possui alunos. Preparacao recusada.');

                return self::FAILURE;
            }

            if (Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]) !== self::SUCCESS) {
                return self::FAILURE;
            }

            $this->info('Estrutura PostgreSQL criada sem semear registros ficticios.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Preparacao interrompida. Confira nomes dos bancos e permissões PostgreSQL.');

            return self::FAILURE;
        } finally {
            $manager->reset();
        }
    }

    private function safeTarget(mixed $settings, string $name): bool
    {
        return is_array($settings)
            && ($settings['driver'] ?? null) === 'pgsql'
            && empty($settings['url'])
            && in_array(strtolower((string) ($settings['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)
            && ($settings['database'] ?? null) === $name
            && is_numeric($settings['port'] ?? null);
    }
}
