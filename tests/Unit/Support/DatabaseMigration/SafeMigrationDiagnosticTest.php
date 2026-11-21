<?php

namespace Tests\Unit\Support\DatabaseMigration;

use App\Support\DatabaseMigration\SafeMigrationDiagnostic;
use InvalidArgumentException;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SafeMigrationDiagnosticTest extends TestCase
{
    public function test_identifica_divergencia_de_tabela_conhecida_sem_dados(): void
    {
        $this->assertSame(
            'Conteudo da tabela audit_logs divergiu apos copia.',
            SafeMigrationDiagnostic::describe(new InvalidArgumentException(
                'Conteudo da tabela audit_logs divergiu apos copia.',
            )),
        );
    }

    public function test_rejeita_mensagem_de_tabela_nao_permitida(): void
    {
        $this->assertSame(
            'Falha interna (InvalidArgumentException).',
            SafeMigrationDiagnostic::describe(new InvalidArgumentException(
                'Conteudo da tabela clientes_reais divergiu apos copia.',
            )),
        );
    }

    public function test_nao_expoe_sql_ou_credenciais_em_falhas_do_driver(): void
    {
        $exception = new PDOException('SQL com credencial-super-secreta e nome de estudante', 0);
        $exception->errorInfo = ['23505', 7, 'detalhe privado do banco'];

        $description = SafeMigrationDiagnostic::describe($exception);

        $this->assertSame('Falha SQL (SQLSTATE 23505).', $description);
        $this->assertStringNotContainsString('credencial-super-secreta', $description);
        $this->assertStringNotContainsString('estudante', $description);
    }

    public function test_nao_imprime_excecoes_internas_imprevistas(): void
    {
        $this->assertSame(
            'Falha interna (RuntimeException).',
            SafeMigrationDiagnostic::describe(new RuntimeException(
                'email e senha de pessoa ficticia: segredo-local',
            )),
        );
    }

    public function test_diagnostica_integridade_conhecida_sem_expor_valores(): void
    {
        $this->assertSame(
            'Integridade da tabela users nao confirmada.',
            SafeMigrationDiagnostic::describe(new InvalidArgumentException(
                'Integridade da tabela users nao confirmada.',
            )),
        );
    }

    public function test_habilita_diagnostico_somente_em_cluster_isolado_verificado(): void
    {
        $platform = [
            'driver' => 'pgsql',
            'url' => null,
            'host' => '127.0.0.1',
            'port' => '55433',
            'database' => 'astuti_platform_dev',
            'username' => 'astuti_ensaio',
        ];
        $tenant = array_replace($platform, ['database' => 'astuti_monte_sereno_dev']);

        $this->assertTrue(SafeMigrationDiagnostic::enabledFor(true, $platform, $tenant));
        $this->assertFalse(SafeMigrationDiagnostic::enabledFor(false, $platform, $tenant));
        $this->assertFalse(SafeMigrationDiagnostic::enabledFor(
            true, array_replace($platform, ['port' => '5432']), $tenant,
        ));
        $this->assertFalse(SafeMigrationDiagnostic::enabledFor(
            true, $platform, array_replace($tenant, ['username' => 'astuti_app']),
        ));
        $this->assertFalse(SafeMigrationDiagnostic::enabledFor(
            true, $platform, array_replace($tenant, ['port' => '5432']),
        ));
    }
}
