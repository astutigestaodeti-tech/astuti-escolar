<?php

namespace Tests\Unit;

use App\Developer\DeploymentHistory;
use PHPUnit\Framework\TestCase;

final class DeploymentHistoryTest extends TestCase
{
    private const OLD = 'ed8859b888e80e467c0f396721b0bab87c6018f0';

    private const MID = 'a7856a1c68f01a9623cdc7e295da41b7dd642942';

    private const NEW = '4de8ea4feb982e549cd263586d6e3413264caa43';

    public function test_descreve_apenas_intervalos_completos_conferidos(): void
    {
        $history = new DeploymentHistory;
        $this->assertSame([248], array_column($history->releases(self::MID, self::NEW), 'pr'));
        $this->assertSame([247, 248], array_column($history->releases(self::OLD, self::NEW), 'pr'));
        $this->assertSame([], $history->releases(str_repeat('a', 40), self::NEW));
        $this->assertSame([], $history->releases(self::MID, str_repeat('b', 40)));
        $this->assertSame([], $history->releases(null, self::NEW));
    }

    public function test_preserva_tentativas_distintas_rollback_e_filtro(): void
    {
        $stages = ['versao_detectada', 'ci_aprovado', 'implantando', 'falha_implantacao', 'recuperando', 'rollback_concluido', 'versao_detectada', 'implantando', 'implantado'];
        $events = [];
        foreach ($stages as $i => $stage) {
            $events[] = ['stage' => $stage, 'previous' => self::MID, 'target' => self::NEW, 'epoch' => 100 + $i];
        }
        $history = new DeploymentHistory;
        $groups = $history->group(array_reverse($events));
        $this->assertCount(2, $groups);
        $this->assertSame('implantado', $groups[0]['latest']['stage']);
        $this->assertSame('rollback_concluido', $groups[1]['latest']['stage']);
        $this->assertSame(2, $groups[0]['elapsed']);
        $this->assertSame(5, $groups[1]['elapsed']);
        $this->assertSame(6, count($groups[1]['events']));
        $this->assertSame([$groups[1]], $history->group(array_reverse($events), 'falha_implantacao'));
    }

    public function test_eventos_sem_identificacao_nao_sao_fundidos(): void
    {
        $event = ['stage' => 'falha_execucao', 'previous' => null, 'target' => null, 'epoch' => 100];
        $this->assertCount(2, (new DeploymentHistory)->group([$event, $event]));
    }
}
