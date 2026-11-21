<?php

namespace Tests\Unit\DatabaseMigration;

use App\Support\DatabaseMigration\MigrationVerifier;
use App\Support\DatabaseMigration\RowContentMismatchException;
use App\Support\DatabaseMigration\SafeMigrationDiagnostic;
use PHPUnit\Framework\TestCase;

class MigrationVerifierDiagnosticTest extends TestCase
{
    public function test_diagnostico_identifica_colunas_sem_expor_valores_privados(): void
    {
        $source = [
            'id' => 'id-privado-123',
            'occurred_at' => '2027-02-01 12:00:00',
            'ip' => '2001:db8::1',
            'after' => '{"nome":"Aluno fictício sigiloso","ativo":true}',
        ];
        $target = [
            'id' => 'id-privado-123',
            'occurred_at' => '2027-02-01 12:00:01',
            'ip' => '2001:db8::2',
            'after' => '{"ativo":true,"nome":"Aluno fictício sigiloso"}',
        ];

        try {
            MigrationVerifier::assertSameRows([$source], [$target], 'platform_audit_logs');
            $this->fail('Diferenças de auditoria precisam interromper a migração.');
        } catch (RowContentMismatchException $exception) {
            $this->assertSame(['ip', 'occurred_at'], $exception->differingColumns());
            $safe = SafeMigrationDiagnostic::describe($exception);
            $this->assertStringContainsString('Campos divergentes: ip, occurred_at.', $safe);
            $this->assertStringNotContainsString('id-privado-123', $safe);
            $this->assertStringNotContainsString('Aluno fictício sigiloso', $safe);
            $this->assertStringNotContainsString('2001:db8::1', $safe);
        }
    }

    public function test_diagnostico_preserva_associacao_entre_identidade_e_coluna(): void
    {
        $source = [
            ['id' => 'primeiro', 'ip' => '127.0.0.1'],
            ['id' => 'segundo', 'ip' => '127.0.0.2'],
        ];
        $target = [
            ['id' => 'segundo', 'ip' => '127.0.0.1'],
            ['id' => 'primeiro', 'ip' => '127.0.0.2'],
        ];

        try {
            MigrationVerifier::assertSameRows($source, $target, 'platform_audit_logs');
            $this->fail('A troca de valores entre registros não pode ser considerada igual.');
        } catch (RowContentMismatchException $exception) {
            $this->assertSame(['ip'], $exception->differingColumns());
        }
    }

    public function test_linha_ausente_aponta_identidade_sem_atribuir_falsas_diferencas_a_outros_campos(): void
    {
        try {
            MigrationVerifier::assertSameRows(
                [['id' => 'identidade-a', 'action' => 'evento fictício', 'role' => 'secretaria']],
                [],
                'platform_audit_logs',
            );
            $this->fail('Uma linha ausente precisa interromper a migração.');
        } catch (RowContentMismatchException $exception) {
            $this->assertSame(['id'], $exception->differingColumns());
        }
    }

    public function test_json_numericamente_equivalente_nao_e_apontado_quando_outro_campo_difere(): void
    {
        try {
            MigrationVerifier::assertSameRows(
                [['id' => 'identidade-a', 'ip' => '127.0.0.1', 'after' => '{"valor":1}']],
                [['id' => 'identidade-a', 'ip' => '127.0.0.2', 'after' => '{"valor":1.0}']],
                'platform_audit_logs',
            );
            $this->fail('O IP divergente precisa interromper a migração.');
        } catch (RowContentMismatchException $exception) {
            $this->assertSame(['ip'], $exception->differingColumns());
        }
    }

    public function test_json_com_chaves_em_ordens_diferentes_nao_e_divergencia(): void
    {
        MigrationVerifier::assertSameRows(
            [['id' => 'ficticio', 'after' => '{"escola":"Monte Sereno","total":1}', 'occurred_at' => '2027-02-01 12:00:00.000000']],
            [['id' => 'ficticio', 'after' => '{"total":1,"escola":"Monte Sereno"}', 'occurred_at' => '2027-02-01 12:00:00']],
            'platform_audit_logs',
        );

        $this->assertTrue(true);
    }
}
