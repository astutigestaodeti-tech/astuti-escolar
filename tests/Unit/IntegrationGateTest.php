<?php

namespace Tests\Unit;

use App\Developer\IntegrationGate;
use PHPUnit\Framework\TestCase;

final class IntegrationGateTest extends TestCase
{
    private function observation(): array
    {
        return [
            'head' => str_repeat('a', 40),
            'main' => str_repeat('b', 40),
            'base' => str_repeat('b', 40),
            'installed' => str_repeat('b', 40),
            'fresh' => true,
            'repository' => 'astutigestaodeti-tech/astuti-escolar',
            'head_repository' => 'astutigestaodeti-tech/astuti-escolar',
            'base_branch' => 'main',
            'open' => true,
            'draft' => false,
            'protected' => true,
            'paths_eligible' => true,
            'threads_resolved' => true,
            'behind' => 0,
            'deployment_healthy' => true,
            'ci' => [
                'head' => str_repeat('a', 40),
                'workflow_id' => 425304833,
                'event' => 'pull_request',
                'status' => 'completed',
                'conclusion' => 'success',
                'jobs' => [
                    'PostgreSQL 17 como usuario web restrito' => 'success',
                    'Testes PHP e interface' => 'success',
                ],
            ],
        ];
    }

    private function decide(array $observation): string
    {
        return (new IntegrationGate)->decide($observation, str_repeat('a', 40), str_repeat('b', 40));
    }

    public function test_exige_revisao_do_sha_e_base_exatos(): void
    {
        $observation = $this->observation();
        $this->assertSame('merge_candidate', $this->decide($observation));
        $this->assertSame('review_required', $this->decide(array_replace($observation, ['head' => str_repeat('c', 40)])));
        $this->assertSame('review_required', $this->decide(array_replace($observation, ['base' => str_repeat('c', 40), 'main' => str_repeat('c', 40)])));
        $this->assertSame('sync_required', $this->decide(array_replace($observation, ['behind' => 1])));
        $this->assertSame('wait_deployment', $this->decide(array_replace($observation, ['installed' => str_repeat('c', 40)])));
    }

    public function test_rejeita_contextos_inseguros_ou_nao_verificados(): void
    {
        $observation = $this->observation();
        foreach (['fresh', 'open', 'protected', 'paths_eligible', 'threads_resolved'] as $key) {
            $this->assertSame('blocked', $this->decide(array_replace($observation, [$key => false])));
        }
        $this->assertSame('blocked', $this->decide(array_replace($observation, ['draft' => true])));
        $this->assertSame('blocked', $this->decide(array_replace($observation, ['head_repository' => 'externo/fork'])));
        $this->assertSame('blocked', $this->decide(array_replace($observation, ['base_branch' => 'feature/test'])));
        $this->assertSame('diagnose', $this->decide(array_replace($observation, ['head' => 'invalid'])));
        $this->assertSame('wait_deployment', $this->decide(array_replace($observation, ['deployment_healthy' => false])));
    }

    public function test_exige_ci_completo_do_workflow_e_sha_certos(): void
    {
        $observation = $this->observation();
        foreach (['head' => str_repeat('c', 40), 'workflow_id' => 42, 'event' => 'push', 'status' => 'in_progress'] as $key => $value) {
            $changed = $observation;
            $changed['ci'][$key] = $value;
            $this->assertSame('wait_ci', $this->decide($changed));
        }
        $changed = $observation;
        $changed['ci']['conclusion'] = 'failure';
        $this->assertSame('diagnose_ci', $this->decide($changed));
        $changed = $observation;
        $changed['ci']['jobs']['Testes PHP e interface'] = 'skipped';
        $this->assertSame('diagnose_ci', $this->decide($changed));
        unset($changed['ci']['jobs']['PostgreSQL 17 como usuario web restrito']);
        $this->assertSame('diagnose_ci', $this->decide($changed));
    }
}
