<?php

namespace App\Console\Commands;

use App\Support\DatabaseMigration\MigrationVerifier;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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

        $sourceP = DB::connection('astuti_source_platform');
        $sourceT = DB::connection('astuti_source_tenant');
        $targetP = DB::connection('platform');
        $targetT = DB::connection('astuti_target_tenant');

        try {
            // MariaDB rejeita INSERT/UPDATE/DELETE na sessao, mesmo se a conta de origem
            // foi provisionada com privilegios excessivos por engano.
            foreach ([$sourceP, $sourceT] as $source) {
                $source->statement('SET SESSION TRANSACTION READ ONLY');
                $source->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $source->beginTransaction();
            }

            $this->verifySchemaAndEmptyTarget($sourceP, $targetP, 'astuti_source_platform', 'platform', MigrationVerifier::PLATFORM_TABLES);
            $this->verifySchemaAndEmptyTarget($sourceT, $targetT, 'astuti_source_tenant', 'astuti_target_tenant', MigrationVerifier::TENANT_TABLES);
            $this->verifyFictitiousFixture($sourceP, $sourceT);

            foreach ([['Central', $sourceP, MigrationVerifier::PLATFORM_TABLES], ['Municipal', $sourceT, MigrationVerifier::TENANT_TABLES]] as [$label, $conn, $tables]) {
                foreach ($tables as $table) {
                    $this->line(sprintf('%s: %s, %d registros de origem.', $label, $table, $conn->table($table)->count()));
                }
            }

            if (! $execute) {
                $this->info('Simulacao aprovada. Nenhum dado foi gravado. Faça backup e execute com a confirmacao quando estiver pronto.');

                return self::SUCCESS;
            }

            // Duas transacoes PG permitem rollback em erro de insercao ou conferencia.
            // NAO ha commit distribuido entre bancos: falha de commit exige
            // inspecao/recuperacao manual dos dois destinos antes de tentar novamente.
            $targetP->beginTransaction();
            $targetT->beginTransaction();

            $this->copyAndVerify($sourceP, $targetP, MigrationVerifier::PLATFORM_TABLES);
            $this->copyAndVerify($sourceT, $targetT, MigrationVerifier::TENANT_TABLES);

            // A sequencia nao avanca com insercao de IDs explicitos.
            $targetP->statement("SELECT setval(pg_get_serial_sequence('municipality_provision_attempts', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM municipality_provision_attempts), 0), 1), (SELECT COUNT(*) > 0 FROM municipality_provision_attempts))");

            // Uma falha aqui bloqueia o corte. A origem MariaDB nao sera modificada.
            $targetT->commit();
            $targetP->commit();

            $this->info('Copia concluida e conferida em ambos os destinos PostgreSQL.');
            $this->warn('Antes do corte, confira o login, as telas, os backups e a restauracao em ambiente separado.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            foreach ([$targetT, $targetP] as $target) {
                if ($target->transactionLevel() > 0) {
                    try {
                        $target->rollBack();
                    } catch (Throwable) {
                        // Uma falha de rollback exige restauracao supervisionada.
                    }
                }
            }

            $this->error('Migracao interrompida por falha de precondicao ou integridade.');
            $this->warn('Nao execute um novo corte sem conferir o estado dos dois destinos. A origem MariaDB permanece intocada.');

            return self::FAILURE;
        } finally {
            foreach ([$sourceP, $sourceT] as $source) {
                if ($source->transactionLevel() > 0) {
                    try {
                        $source->rollBack();
                    } catch (Throwable) {
                        // Apenas encerra a leitura consistente.
                    }
                }
            }

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

    private function verifySchemaAndEmptyTarget(Connection $source, Connection $target, string $sourceName, string $targetName, array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::connection($sourceName)->hasTable($table) || ! Schema::connection($targetName)->hasTable($table)) {
                throw new RuntimeException('Tabela esperada nao encontrada.');
            }

            $from = Schema::connection($sourceName)->getColumnListing($table);
            $to = Schema::connection($targetName)->getColumnListing($table);

            if (array_diff($from, $to) !== []) {
                throw new RuntimeException('Estruturas de tabela incompatíveis.');
            }

            if ($target->table($table)->exists()) {
                throw new RuntimeException('Destino contem registros. Migracao recusada.');
            }
        }
    }

    private function verifyFictitiousFixture(Connection $platform, Connection $tenant): void
    {
        if ($platform->table('municipalities')->count() !== 1
            || ! $platform->table('municipalities')->where('slug', 'monte-sereno')->where('db_key', 'monte_sereno')->exists()
            || $tenant->table('schools')->count() !== 3
            || $tenant->table('students')->count() !== 115
            || $tenant->table('school_years')->where('year', 2027)->count() !== 1
            || $tenant->table('school_years')->count() !== 1
            || $tenant->table('students')->where('full_name', 'not like', 'Estudante fictício %')->exists()
            || $platform->table('users')->where('email', 'not like', '%.invalid')->exists()) {
            throw new RuntimeException('Origem nao corresponde ao cenario ficticio Monte Sereno v2.');
        }
    }

    private function copyAndVerify(Connection $source, Connection $target, array $tables): void
    {
        foreach ($tables as $table) {
            $rows = $source->table($table)->get()->map(static fn ($row): array => (array) $row)->all();

            foreach (array_chunk($rows, 50) as $chunk) {
                $target->table($table)->insert($chunk);
            }

            MigrationVerifier::assertSameRows(
                $rows,
                $target->table($table)->get()->map(static fn ($row): array => (array) $row)->all(),
                $table,
            );

            $keys = $table === 'school_scopes' ? ['membership_id', 'school_id'] : ['id'];
            $fromIds = $this->identityList($source, $table, $keys);
            $toIds = $this->identityList($target, $table, $keys);
            MigrationVerifier::assertSameIdentity($fromIds, $toIds, $table);

            if ($source->table($table)->count() !== $target->table($table)->count()) {
                throw new RuntimeException('Contagens divergentes apos copia.');
            }
        }

        if ($tables === MigrationVerifier::PLATFORM_TABLES) {
            $from = $source->table('users')->pluck('password', 'id')->all();
            $to = $target->table('users')->pluck('password', 'id')->all();

            foreach ($from as $userId => $originalHash) {
                MigrationVerifier::preservePasswordHash((string) $originalHash, (string) ($to[$userId] ?? ''));
            }
        }
    }

    private function identityList(Connection $connection, string $table, array $columns): array
    {
        return $connection->table($table)->get($columns)->map(
            static fn ($row): string => implode('|', array_map(static fn ($column): string => (string) $row->{$column}, $columns)),
        )->all();
    }
}
