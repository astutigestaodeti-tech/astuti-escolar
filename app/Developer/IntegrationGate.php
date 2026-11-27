<?php

namespace App\Developer;

/**
 * Política offline. Somente observações de um coletor confiável e revisão
 * registrada localmente podem alimentar esta classe; não autoriza escrita.
 */
final class IntegrationGate
{
    private const REPOSITORY = 'astutigestaodeti-tech/astuti-escolar';

    private const WORKFLOW_ID = 425304833;

    private const JOBS = ['PostgreSQL 17 como usuario web restrito', 'Testes PHP e interface'];

    public function decide(array $observation, string $reviewedHead, string $reviewedBase): string
    {
        foreach ([$reviewedHead, $reviewedBase, $observation['head'] ?? null, $observation['main'] ?? null, $observation['base'] ?? null, $observation['installed'] ?? null] as $sha) {
            if (! is_string($sha) || ! preg_match('/\\A[a-f0-9]{40}\\z/D', $sha)) {
                return 'diagnose';
            }
        }

        if (($observation['fresh'] ?? null) !== true
            || ($observation['repository'] ?? null) !== self::REPOSITORY
            || ($observation['head_repository'] ?? null) !== self::REPOSITORY
            || ($observation['base_branch'] ?? null) !== 'main'
            || ($observation['open'] ?? null) !== true
            || ($observation['draft'] ?? null) !== false
            || ($observation['protected'] ?? null) !== true
            || ($observation['paths_eligible'] ?? null) !== true
            || ($observation['threads_resolved'] ?? null) !== true) {
            return 'blocked';
        }

        if (($observation['behind'] ?? null) !== 0 || $observation['base'] !== $observation['main']) {
            return 'sync_required';
        }

        if ($observation['head'] !== $reviewedHead || $observation['main'] !== $reviewedBase) {
            return 'review_required';
        }

        if ($observation['installed'] !== $observation['main'] || ($observation['deployment_healthy'] ?? null) !== true) {
            return 'wait_deployment';
        }

        $ci = $observation['ci'] ?? null;
        if (! is_array($ci) || ($ci['head'] ?? null) !== $reviewedHead
            || ($ci['workflow_id'] ?? null) !== self::WORKFLOW_ID
            || ($ci['event'] ?? null) !== 'pull_request') {
            return 'wait_ci';
        }
        if (($ci['status'] ?? null) !== 'completed') {
            return 'wait_ci';
        }
        if (($ci['conclusion'] ?? null) !== 'success') {
            return 'diagnose_ci';
        }

        $jobs = $ci['jobs'] ?? null;
        if (! is_array($jobs) || count($jobs) !== count(self::JOBS)) {
            return 'diagnose_ci';
        }
        foreach (self::JOBS as $name) {
            if (($jobs[$name] ?? null) !== 'success') {
                return 'diagnose_ci';
            }
        }

        return 'merge_candidate';
    }
}
