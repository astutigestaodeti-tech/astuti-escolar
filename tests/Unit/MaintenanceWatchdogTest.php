<?php

namespace Tests\Unit;

use App\Developer\MaintenanceWatchdog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaintenanceWatchdogTest extends TestCase
{
    public static function decisions(): array
    {
        return [
            'antes do limite' => ['code_failure', 1799, 0, 'wait'],
            'limite inclusivo' => ['code_failure', 1800, 0, 'prepare_fix_pr'],
            'ci ativo nunca cancelar' => ['active_ci', 1800, 0, 'diagnose'],
            'transitorio comprovado' => ['transient_ci_failure', 1800, 0, 'propose_ci_retry'],
            'nao repetir ci' => ['transient_ci_failure', 1800, 1, 'root_cause_review'],
            'limite de tentativas' => ['code_failure', 1800, 2, 'root_cause_review'],
            'implantacao parada' => ['deployment_wait', 1800, 0, 'diagnose_deployment'],
            'implantacao falhou' => ['deployment_failure', 1800, 1, 'diagnose_deployment'],
            'protecao imediata' => ['security_block', 1, 0, 'escalate'],
            'backup ausente' => ['missing_backup', 1, 0, 'escalate'],
            'estado desconhecido' => ['unknown', 1800, 0, 'escalate'],
            'estado fora do catalogo' => ['shell', 1800, 0, 'diagnose'],
            'saudavel' => ['healthy', 3600, 0, 'wait'],
        ];
    }

    #[DataProvider('decisions')]
    public function test_politica_nao_executa_acoes_e_limita_tentativas(string $state, int $elapsed, int $attempts, string $expected): void
    {
        $result = (new MaintenanceWatchdog)->decide($state, 10000 - $elapsed, 10000, 10000, $attempts);
        $this->assertSame($expected, $result['action']);
    }

    public function test_coleta_velha_ou_incoerente_nao_autoriza_correcao(): void
    {
        $watchdog = new MaintenanceWatchdog;
        $this->assertSame('stale_observation', $watchdog->decide('code_failure', 8000, 9800, 10000, 0)['reason']);
        $this->assertSame('invalid_observation', $watchdog->decide('code_failure', 10001, 10000, 10000, 0)['reason']);
        $this->assertSame('invalid_observation', $watchdog->decide('code_failure', 8000, 10001, 10000, 0)['reason']);
        $this->assertSame('invalid_observation', $watchdog->decide('code_failure', 8000, 10000, 10000, -1)['reason']);
    }
}
