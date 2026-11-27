<?php

namespace App\Developer;

/**
 * Política offline, sem I/O ou execução. As observações devem vir de
 * coletores confiáveis e o contador de tentativas de um registro durável.
 */
final class MaintenanceWatchdog
{
    public const STALL_SECONDS = 1800;

    public const MAX_ATTEMPTS = 2;

    private const MAX_OBSERVATION_AGE = 150;

    /**
     * @return array{action:string,elapsed:?int,reason:string}
     */
    public function decide(string $state, int $lastProgress, int $observedAt, int $now, int $attempts): array
    {
        $decision = static fn (string $action, ?int $elapsed, string $reason): array => [
            'action' => $action, 'elapsed' => $elapsed, 'reason' => $reason,
        ];

        if ($lastProgress <= 0 || $observedAt < $lastProgress || $now < $observedAt || $attempts < 0) {
            return $decision('diagnose', null, 'invalid_observation');
        }

        if ($now - $observedAt > self::MAX_OBSERVATION_AGE) {
            return $decision('diagnose', null, 'stale_observation');
        }

        $elapsed = $now - $lastProgress;
        if (in_array($state, ['security_block', 'missing_backup', 'unknown'], true)) {
            return $decision('escalate', $elapsed, 'unsafe_or_unknown');
        }
        if ($state === 'healthy') {
            return $decision('wait', $elapsed, 'healthy');
        }
        if (! in_array($state, ['active_ci', 'transient_ci_failure', 'code_failure', 'deployment_wait', 'deployment_failure'], true)) {
            return $decision('diagnose', null, 'unknown_state');
        }

        if ($elapsed < self::STALL_SECONDS) {
            return $decision('wait', $elapsed, 'within_threshold');
        }

        if ($state === 'active_ci') {
            return $decision('diagnose', $elapsed, 'check_active_ci_without_cancelling');
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            return $decision('root_cause_review', $elapsed, 'attempt_limit');
        }

        return match ($state) {
            'transient_ci_failure' => $attempts === 0
                ? $decision('propose_ci_retry', $elapsed, 'verified_transient_failure')
                : $decision('root_cause_review', $elapsed, 'retry_already_attempted'),
            'code_failure' => $decision('prepare_fix_pr', $elapsed, 'code_or_test_failure'),
            'deployment_wait', 'deployment_failure' => $decision('diagnose_deployment', $elapsed, 'check_local_recovery_policy'),
            default => $decision('diagnose', $elapsed, 'unknown_state'),
        };
    }
}
