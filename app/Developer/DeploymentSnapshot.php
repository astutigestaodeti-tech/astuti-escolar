<?php

namespace App\Developer;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Contrato de leitura do console técnico. O coletor publica um snapshot
 * privado, independente do diretório de estado e dos logs brutos do agente.
 */
final class DeploymentSnapshot
{
    public const PATH = '/var/lib/astuti-agent-view/snapshot.json';

    private const MAX_BYTES = 524288;

    private const MAX_EVENTS = 1000;

    // Coleta a cada 120 segundos, com tolerância de 30 segundos.
    private const MAX_AGE = 150;

    private const RETENTION = 7776000;

    private const LABELS = [
        'versao_detectada' => 'Nova versão detectada',
        'aguardando_ci' => 'Aguardando testes',
        'ci_aprovado' => 'Testes aprovados',
        'preparando_backup' => 'Preparando backup',
        'implantando' => 'Implantando versão',
        'validando' => 'Validando versão',
        'implantado' => 'Implantação concluída',
        'implantacao_especial' => 'Implantação especial necessária',
        'versao_bloqueada' => 'Versão bloqueada',
        'falha_implantacao' => 'Falha na implantação',
        'recuperando' => 'Recuperação em andamento',
        'rollback_concluido' => 'Versão anterior restaurada',
        'falha_execucao' => 'Falha na execução do agente',
    ];

    public static function stages(): array
    {
        return self::LABELS;
    }

    public function read(int $now): array
    {
        clearstatcache(true, self::PATH);
        if (realpath(self::PATH) !== self::PATH || ! is_file(self::PATH)) {
            return $this->unavailable();
        }

        // O arquivo é publicado por substituição atômica; não ler .git,
        // estado privado, .env nem caminhos recebidos de uma requisição.
        $file = @fopen(self::PATH, 'rb');
        if ($file === false) {
            return $this->unavailable();
        }

        try {
            $raw = stream_get_contents($file, self::MAX_BYTES + 1);
        } finally {
            fclose($file);
        }

        return is_string($raw) ? $this->decode($raw, $now) : $this->unavailable();
    }

    public function decode(string $raw, int $now): array
    {
        if (strlen($raw) > self::MAX_BYTES) {
            return $this->unavailable();
        }

        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->unavailable();
        }

        if (! is_array($data)
            || ($data['schema'] ?? null) !== 1
            || ! is_int($data['collected_at'] ?? null)
            || $data['collected_at'] <= 0
            || $data['collected_at'] > $now
            || ! is_array($data['events'] ?? null)
            || ! array_is_list($data['events'])
            || count($data['events']) > self::MAX_EVENTS) {
            return $this->unavailable();
        }

        $events = [];
        foreach ($data['events'] as $sequence => $event) {
            if (! is_array($event)) {
                continue;
            }

            $stage = $event['etapa'] ?? null;
            $epoch = $event['epoch'] ?? null;
            if (! is_string($stage)
                || ! isset(self::LABELS[$stage])
                || (! is_int($epoch) && ! is_float($epoch))
                || ! is_finite((float) $epoch)
                || $epoch <= 0
                || $epoch < $now - self::RETENTION
                || $epoch > $data['collected_at']) {
                continue;
            }

            $run = $event['ci'] ?? null;
            // Reconstruir a saída a partir de campos e estados permitidos.
            // Mensagens, caminhos, identidades e demais campos são descartados.
            $events[] = [
                'stage' => $stage,
                'label' => self::LABELS[$stage],
                'epoch' => $epoch,
                'sequence' => $sequence,
                'time' => $this->localTime((int) $epoch),
                'previous' => $this->sha($event['anterior'] ?? null),
                'target' => $this->sha($event['alvo'] ?? null),
                'ci' => is_int($run) && $run > 0 ? $run : null,
            ];
        }

        usort($events, static fn (array $a, array $b): int => ($b['epoch'] <=> $a['epoch'])
            ?: ($b['sequence'] <=> $a['sequence']));

        return [
            'available' => true,
            'stale' => $now - $data['collected_at'] > self::MAX_AGE,
            'collected_at' => $this->localTime($data['collected_at']),
            'installed' => $this->sha($data['installed'] ?? null),
            'target' => $this->sha($data['target'] ?? null),
            'blocked' => $this->sha($data['blocked'] ?? null),
            'events' => $events,
        ];
    }

    private function sha(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{40}\z/D', $value) === 1
            ? $value
            : null;
    }

    private function localTime(int $epoch): string
    {
        return (new DateTimeImmutable('@'.$epoch))
            ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
            ->format('d/m/Y H:i:s');
    }

    private function unavailable(): array
    {
        return [
            'available' => false,
            'stale' => true,
            'collected_at' => null,
            'installed' => null,
            'target' => null,
            'blocked' => null,
            'events' => [],
        ];
    }
}
