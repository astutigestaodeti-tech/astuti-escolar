<?php

namespace Tests\Unit;

use App\Developer\MaintenanceLedger;
use App\Developer\MaintenanceRequest;
use PDO;
use PHPUnit\Framework\TestCase;

final class MaintenanceLedgerTest extends TestCase
{
    private const NOW = 1795720000;

    private string $path;

    private string $pair;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'astuti-ledger-');
        $this->pair = sodium_crypto_sign_keypair();
    }

    protected function tearDown(): void
    {
        foreach ([$this->path, $this->path.'-journal', $this->path.'-wal', $this->path.'-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function ledger(string $installation = 'monte-sereno'): MaintenanceLedger
    {
        return new MaintenanceLedger(new PDO('sqlite:'.$this->path), $installation);
    }

    private function payload(int $sequence = 8, string $nonce = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', string $operation = 'diagnostico.saude'): string
    {
        return json_encode([
            'schema' => 1,
            'installation' => 'monte-sereno',
            'sequence' => $sequence,
            'nonce' => $nonce,
            'issued_at' => self::NOW - 10,
            'expires_at' => self::NOW + 60,
            'operation' => $operation,
        ], JSON_THROW_ON_ERROR);
    }

    private function reserve(MaintenanceLedger $ledger, string $payload, int $now = self::NOW): string
    {
        return $ledger->reserve(
            $payload,
            bin2hex(sodium_crypto_sign_detached(MaintenanceRequest::DOMAIN.$payload, sodium_crypto_sign_secretkey($this->pair))),
            bin2hex(sodium_crypto_sign_publickey($this->pair)),
            $now,
        );
    }

    public function test_reserva_persiste_em_outra_conexao_e_resultado_e_idempotente(): void
    {
        $first = $this->ledger();
        $first->initialize();
        $payload = $this->payload();
        $nonce = str_repeat('a', 32);
        $digest = hash('sha256', $payload);
        $this->assertSame('reserved', $this->reserve($first, $payload));
        unset($first);
        $second = $this->ledger();
        $this->assertSame('pending', $this->reserve($second, $payload));
        $this->assertFalse($second->finish(8, $nonce, str_repeat('0', 64), 'succeeded'));
        $this->assertTrue($second->finish(8, $nonce, $digest, 'succeeded'));
        $this->assertTrue($second->finish(8, $nonce, $digest, 'succeeded'));
        $this->assertFalse($second->finish(8, $nonce, $digest, 'failed'));
        unset($second);
        $this->assertSame('succeeded', $this->reserve($this->ledger(), $payload));
    }

    public function test_rejeita_sequencia_antiga_nonce_reutilizado_e_pedido_alterado(): void
    {
        $ledger = $this->ledger();
        $ledger->initialize();
        $this->assertSame('reserved', $this->reserve($ledger, $this->payload()));
        $this->assertSame('rejected', $this->reserve($ledger, $this->payload(9)));
        $this->assertSame('rejected', $this->reserve($ledger, $this->payload(8, str_repeat('b', 32))));
        $this->assertSame('rejected', $this->reserve($ledger, $this->payload(7, str_repeat('b', 32))));
        $this->assertSame('rejected', $this->reserve($ledger, $this->payload(8, str_repeat('a', 32), 'diagnostico.implantacao')));
        $this->assertSame('reserved', $this->reserve($ledger, $this->payload(9, str_repeat('b', 32))));
        $this->assertSame('rejected', $this->reserve($ledger, $this->payload(10, str_repeat('c', 32)), self::NOW + 60));
    }

    public function test_estado_ausente_ou_incompativel_falha_fechado_sem_reinicializar(): void
    {
        $ledger = $this->ledger();
        $this->assertSame('rejected', $this->reserve($ledger, $this->payload()));
        $ledger->initialize();
        $this->assertSame('reserved', $this->reserve($ledger, $this->payload()));
        $other = $this->ledger('outra-instalacao');
        $this->assertSame('rejected', $this->reserve($other, $this->payload()));
        $this->assertFalse($other->finish(8, str_repeat('a', 32), hash('sha256', $this->payload()), 'succeeded'));
        $this->assertSame('pending', $this->reserve($ledger, $this->payload()));
    }
}
