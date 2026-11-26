<?php

namespace Tests\Unit\Developer;

use App\Developer\DeploymentSnapshot;
use PHPUnit\Framework\TestCase;

final class DeploymentSnapshotTest extends TestCase
{
    private const NOW = 1795708800;

    private function snapshot(array $events, array $overrides = []): array
    {
        $data = array_replace([
            'schema' => 1,
            'collected_at' => self::NOW,
            'installed' => str_repeat('a', 40),
            'target' => str_repeat('b', 40),
            'blocked' => null,
            'events' => $events,
        ], $overrides);

        return (new DeploymentSnapshot)->decode(json_encode($data, JSON_THROW_ON_ERROR), self::NOW);
    }

    public function test_events_are_sorted_newest_first_with_stable_tie_break(): void
    {
        $data = $this->snapshot([
            ['etapa' => 'validando', 'epoch' => self::NOW - 2],
            ['etapa' => 'implantando', 'epoch' => self::NOW - 5],
            ['etapa' => 'implantado', 'epoch' => self::NOW - 2, 'ci' => 123],
        ]);

        self::assertTrue($data['available']);
        self::assertFalse($data['stale']);
        self::assertSame(['implantado', 'validando', 'implantando'], array_column($data['events'], 'stage'));
        self::assertSame(123, $data['events'][0]['ci']);
        self::assertSame(str_repeat('a', 40), $data['installed']);
    }

    public function test_rejects_unknown_stages_future_and_expired_events_and_strips_other_fields(): void
    {
        $data = $this->snapshot([
            ['etapa' => 'secret', 'epoch' => self::NOW],
            ['etapa' => 'implantado', 'epoch' => self::NOW + 1],
            ['etapa' => 'implantado', 'epoch' => self::NOW - 7776001],
            ['etapa' => 'falha_execucao', 'epoch' => self::NOW, 'message' => 'secret', 'alvo' => '<script>', 'ci' => 'http://example.invalid'],
        ], ['token' => 'secret', 'blocked' => 'invalid']);

        self::assertCount(1, $data['events']);
        self::assertSame('falha_execucao', $data['events'][0]['stage']);
        self::assertNull($data['events'][0]['target']);
        self::assertNull($data['events'][0]['ci']);
        self::assertNull($data['blocked']);
        self::assertStringNotContainsString('secret', json_encode($data, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('<script>', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function test_stale_snapshot_preserves_history_without_inventing_health(): void
    {
        $data = $this->snapshot([
            ['etapa' => 'implantado', 'epoch' => self::NOW - 180],
            ['etapa' => 'rollback_concluido', 'epoch' => self::NOW - 160],
        ], ['collected_at' => self::NOW - 151]);

        self::assertTrue($data['available']);
        self::assertTrue($data['stale']);
        self::assertSame('rollback_concluido', $data['events'][0]['stage']);
    }

    public function test_freshness_matches_two_minute_collection_with_tolerance(): void
    {
        self::assertFalse($this->snapshot([], ['collected_at' => self::NOW - 120])['stale']);
        self::assertFalse($this->snapshot([], ['collected_at' => self::NOW - 150])['stale']);
        self::assertTrue($this->snapshot([], ['collected_at' => self::NOW - 151])['stale']);
    }

    public function test_invalid_or_oversized_snapshots_fail_closed(): void
    {
        $reader = new DeploymentSnapshot;
        foreach (['', '{', 'null', str_repeat('a', 524289)] as $raw) {
            self::assertFalse($reader->decode($raw, self::NOW)['available']);
        }

        self::assertFalse($this->snapshot([], ['schema' => 2])['available']);
        self::assertFalse($this->snapshot([], ['collected_at' => self::NOW + 1])['available']);
        self::assertFalse($this->snapshot(array_fill(0, 1001, []))['available']);
        self::assertFalse($this->snapshot(['event' => []])['available']);
    }

    public function test_empty_snapshot_is_not_an_invented_deployment(): void
    {
        $data = $this->snapshot([], ['installed' => null, 'target' => null]);

        self::assertTrue($data['available']);
        self::assertNull($data['installed']);
        self::assertSame([], $data['events']);
    }
}
