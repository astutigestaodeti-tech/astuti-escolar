<?php

namespace App\Console\Commands;

use App\Support\DatabaseMigration\FictitiousDataTransfer;
use App\Support\DatabaseMigration\MigrationVerifier;
use App\Support\DatabaseMigration\PartialCommitException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateFictitiousDataToPostgres extends Command
{
    protected $signature = 'astuti:migrar-dados-postgresql {--executar : Copiar apos a conferencia} {--confirmar= : Confirmacao MONTE-SERENO-DEV}';

    protected $description = 'Confere e, mediante autorizacao explicita, copia a demo ficticia do MariaDB local para PostgreSQL.';

    public function handle(): int
    {
        // Checagem ANTES de abrir qualquer conexao; jamais executar em teste ou producao.
        if (! app()->environment('local')) {
            $this->error('Comando exclusivo do desenvolvimento local ficticio.');

            return self::FAILURE;
        }

        $platform = config('database.connections.platform', []);
        $tenant = config('tenancy.connections.monte_sereno', []);
        $sourcePlatform = $this->sourceSettings('astuti_platform_dev');
        $sourceTenant = $this->sourceSettings('astuti_monte_sereno_dev');

        if (! is_array($tenant) || ! is_array($platform)
            || ! MigrationVerifier::safeContext('local', $platform, $tenant, $sourcePlatform)
            || ($sourcePlatform['username'] ?? '') === ''
            || ($sourcePlatform['password'] ?? null) === null
            || ($sourceTenant['database'] ?? null) !== 'astuti_monte_sereno_dev') {
            $this->error('Configuracao de migracao ausente ou bases fora do ambiente ficticio permitido.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('executar');

        if ($execute && $this->option('confirmar') !== 'MONTE-SERENO-DEV') {
            $this->error('Para copiar, use --executar --confirmar=MONTE-SERENO-DEV.');

            return self::FAILURE;
        }

        config()->set('database.connections.astuti_source_platform', $sourcePlatform);
        config()->set('database.connections.astuti_source_tenant', $sourceTenant);
        config()->set('database.connections.astuti_target_tenant', $tenant);

        try {
            $counts = (new FictitiousDataTransfer)->run(
                DB::connection('astuti_source_platform'),
                DB::connection('astuti_source_tenant'),
                DB::connection('platform'),
                DB::connection('astuti_target_tenant'),
                $execute,
            );

            foreach (['Central' => $counts['platform'], 'Municipal' => $counts['tenant']] as $label => $tables) {
                foreach ($tables as $table => $count) {
                    $this->line(sprintf('%s: %s, %d registros de origem.', $label, $table, $count));
                }
            }

            if (! $execute) {
                $this->info('Simulacao aprovada. Nenhum dado foi gravado. Faça backup e execute com a confirmacao quando estiver pronto.');

                return self::SUCCESS;
            }

            $this->info('Copia concluida e conferida em ambos os destinos PostgreSQL.');
            $this->warn('Antes do corte, confira o login, as telas, os backups e a restauracao em ambiente separado.');

            return self::SUCCESS;
        } catch (PartialCommitException $exception) {
            $this->error($exception->getMessage());
            $this->warn('Mantenha a aplicação em manutenção e siga a recuperação supervisionada em docs/operacao/migracao-mariadb-postgresql.md.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Migracao interrompida por falha de precondicao ou integridade.');
            $this->warn('Nao execute um novo corte sem conferir o estado dos dois destinos. A origem MariaDB permanece intocada.');

            return self::FAILURE;
        } finally {
            foreach (['astuti_source_platform', 'astuti_source_tenant', 'astuti_target_tenant'] as $name) {
                DB::purge($name);
                config()->offsetUnset("database.connections.{$name}");
            }
        }
    }

    private function sourceSettings(string $database): array
    {
        return [
            'driver' => 'mysql',
            'url' => null,
            'host' => env('ASTUTI_SOURCE_MARIADB_HOST', '127.0.0.1'),
            'port' => env('ASTUTI_SOURCE_MARIADB_PORT', '3307'),
            'database' => $database,
            'username' => env('ASTUTI_SOURCE_MARIADB_USERNAME', ''),
            'password' => env('ASTUTI_SOURCE_MARIADB_PASSWORD'),
            'unix_socket' => env('ASTUTI_SOURCE_MARIADB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }
}
