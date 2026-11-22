<?php

namespace App\Support\DatabaseMigration;

use App\Support\EmailNormalizer;
use Closure;
use Illuminate\Database\Connection;
use RuntimeException;
use Throwable;

final class FictitiousDataTransfer
{
    /**
     * Use quatro conexões dedicadas, sem transações abertas pelo chamador.
     *
     * @return array{platform: array<string, int>, tenant: array<string, int>}
     */
    public function run(
        Connection $sourcePlatform,
        Connection $sourceTenant,
        Connection $targetPlatform,
        Connection $targetTenant,
        bool $execute,
        ?Closure $afterMunicipalCommit = null,
        ?Closure $onStage = null,
    ): array {
        try {
            foreach ([$sourcePlatform, $sourceTenant] as $source) {
                // Protege a leitura sem alterar as proximas transacoes da conexao recebida.
                $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                $source->beginTransaction();
            }

            $this->verifySchemaAndEmptyTarget($sourcePlatform, $targetPlatform, MigrationVerifier::PLATFORM_TABLES);
            $this->verifySchemaAndEmptyTarget($sourceTenant, $targetTenant, MigrationVerifier::TENANT_TABLES);
            $this->verifyFictitiousFixture($sourcePlatform, $sourceTenant);
            $this->verifyInterdatabaseReferences($sourcePlatform, $sourceTenant);

            $counts = [
                'platform' => $this->counts($sourcePlatform, MigrationVerifier::PLATFORM_TABLES),
                'tenant' => $this->counts($sourceTenant, MigrationVerifier::TENANT_TABLES),
            ];

            if (! $execute) {
                return $counts;
            }

            $targetPlatform->beginTransaction();
            $targetTenant->beginTransaction();

            $this->copyAndVerify($sourcePlatform, $targetPlatform, MigrationVerifier::PLATFORM_TABLES, $onStage);
            $this->copyAndVerify($sourceTenant, $targetTenant, MigrationVerifier::TENANT_TABLES, $onStage);

            // A sequencia nao avanca com insercao de IDs explicitos.
            $onStage?->__invoke('ajustando', 'municipality_provision_attempts');
            $targetPlatform->statement("SELECT setval(pg_get_serial_sequence('municipality_provision_attempts', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM municipality_provision_attempts), 0), 1), (SELECT COUNT(*) > 0 FROM municipality_provision_attempts))");

            // Os dois bancos PostgreSQL nao compartilham uma transacao.
            $targetTenant->commit();
            try {
                $afterMunicipalCommit?->__invoke();
                $targetPlatform->commit();
            } catch (Throwable $exception) {
                throw new PartialCommitException($exception);
            }

            return $counts;
        } catch (Throwable $exception) {
            $this->rollback($targetTenant);
            $this->rollback($targetPlatform);

            throw $exception;
        } finally {
            $this->rollback($sourceTenant);
            $this->rollback($sourcePlatform);
        }
    }

    private function rollback(Connection $connection): void
    {
        if ($connection->transactionLevel() === 0) {
            return;
        }

        try {
            $connection->rollBack();
        } catch (Throwable) {
            // Preserva a falha original para permitir a inspecao dos destinos.
        }
    }

    private function counts(Connection $connection, array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = $connection->table($table)->count();
        }

        return $counts;
    }

    private function verifySchemaAndEmptyTarget(Connection $source, Connection $target, array $tables): void
    {
        $sourceSchema = $source->getSchemaBuilder();
        $targetSchema = $target->getSchemaBuilder();

        foreach ($tables as $table) {
            if (! $sourceSchema->hasTable($table) || ! $targetSchema->hasTable($table)) {
                throw new RuntimeException('Tabela esperada nao encontrada.');
            }

            $from = $sourceSchema->getColumnListing($table);
            $to = $targetSchema->getColumnListing($table);

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

    private function verifyInterdatabaseReferences(Connection $platform, Connection $tenant): void
    {
        $centralUsers = $platform->table('users')->pluck('id')->all();
        $municipalSchools = $tenant->table('schools')->pluck('id')->all();

        foreach ([
            [$platform, 'school_scopes', 'school_id', $municipalSchools],
            [$platform, 'platform_audit_logs', 'user_id', $centralUsers],
            [$tenant, 'audit_logs', 'user_id', $centralUsers],
        ] as [$connection, $table, $column, $validIds]) {
            $referencedIds = $connection->table($table)->whereNotNull($column)->distinct()->pluck($column)->all();

            if (array_diff($referencedIds, $validIds) !== []) {
                throw new RuntimeException("Referência inválida em {$table}.{$column}.");
            }
        }
    }

    private function copyAndVerify(Connection $source, Connection $target, array $tables, ?Closure $onStage): void
    {
        foreach ($tables as $table) {
            $onStage?->__invoke('inserindo', $table);
            $sourceColumns = $source->getSchemaBuilder()->getColumnListing($table);
            $rows = $source->table($table)->get($sourceColumns)->map(static fn ($row): array => (array) $row)->all();

            // A origem MariaDB pode conter emails mistos. Preservar o hash,
            // os UUIDs e demais campos, mas gravar o email canonico no PostgreSQL.
            if ($table === 'users') {
                $rows = array_map(static function (array $row): array {
                    $row['email'] = EmailNormalizer::normalize((string) $row['email']);

                    return $row;
                }, $rows);
            }

            foreach (array_chunk($rows, 50) as $chunk) {
                $target->table($table)->insert($chunk);
            }

            $onStage?->__invoke('conferindo', $table);
            MigrationVerifier::assertSameRows(
                $rows,
                // O destino pode ter campos novos NULL sem correspondente no MariaDB.
                $target->table($table)->get($sourceColumns)->map(static fn ($row): array => (array) $row)->all(),
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
