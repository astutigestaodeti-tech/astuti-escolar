<?php

namespace App\Developer;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Componente offline para o futuro consumidor isolado.
 * Recebe conexão privada previamente provisionada; nunca abre um caminho vindo
 * de requisição web. Não executa diagnósticos, não concede privilégios.
 */
final class MaintenanceLedger
{
    private const LIMIT = 100000;

    public function __construct(private readonly PDO $db, private readonly string $installation)
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite'
            || ! preg_match('/\A[a-z0-9][a-z0-9-]{0,62}\z/D', $installation)) {
            throw new RuntimeException('Configuração de estado inválida.');
        }

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout = 3000');
        $db->exec('PRAGMA synchronous = FULL');
    }

    /** Provisionamento explícito, exclusivamente em banco privado novo. */
    public function initialize(): void
    {
        $this->transaction(function (): bool {
            $this->db->exec('CREATE TABLE ledger_scope (id INTEGER PRIMARY KEY CHECK (id = 1), installation TEXT NOT NULL, last_sequence INTEGER NOT NULL CHECK (last_sequence >= 0), used INTEGER NOT NULL CHECK (used >= 0))');
            $this->db->exec("CREATE TABLE ledger_requests (sequence INTEGER PRIMARY KEY CHECK (sequence > 0), nonce TEXT NOT NULL UNIQUE, digest TEXT NOT NULL, result TEXT NOT NULL CHECK (result IN ('pending', 'succeeded', 'failed')))");
            $statement = $this->db->prepare('INSERT INTO ledger_scope VALUES (1, ?, 0, 0)');
            $statement->execute([$this->installation]);

            return true;
        });
    }

    /**
     * Somente reserved permite iniciar uma operação futura, uma única vez.
     * pending significa resultado desconhecido: nunca repetir automaticamente.
     * Erros de armazenamento, escopo, assinatura ou validade rejeitam o pedido.
     */
    public function reserve(string $payload, string $signatureHex, string $publicKeyHex, int $now): string
    {
        try {
            $request = (new MaintenanceRequest)->validate($payload, $signatureHex, $publicKeyHex, $this->installation, 0, $now);
            if ($request === null) {
                return 'rejected';
            }

            $digest = hash('sha256', $payload);

            return $this->transaction(function () use ($request, $digest): string {
                $scope = $this->scope();
                $statement = $this->db->prepare('SELECT sequence, nonce, digest, result FROM ledger_requests WHERE sequence = ? OR nonce = ?');
                $statement->execute([$request['sequence'], $request['nonce']]);
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                if ($rows !== []) {
                    $row = $rows[0];

                    return count($rows) === 1
                        && (int) $row['sequence'] === $request['sequence']
                        && hash_equals($row['nonce'], $request['nonce'])
                        && hash_equals($row['digest'], $digest)
                        && in_array($row['result'], ['pending', 'succeeded', 'failed'], true)
                        ? $row['result']
                        : 'rejected';
                }

                if ($request['sequence'] <= (int) $scope['last_sequence'] || (int) $scope['used'] >= self::LIMIT) {
                    return 'rejected';
                }

                $statement = $this->db->prepare("INSERT INTO ledger_requests (sequence, nonce, digest, result) VALUES (?, ?, ?, 'pending')");
                $statement->execute([$request['sequence'], $request['nonce'], $digest]);
                $statement = $this->db->prepare('UPDATE ledger_scope SET last_sequence = ?, used = used + 1 WHERE id = 1');
                $statement->execute([$request['sequence']]);

                return 'reserved';
            });
        } catch (Throwable) {
            return 'rejected';
        }
    }

    /**
     * Finalização pelo consumidor local confiável; não é endpoint nem API remota.
     * O identificador deve corresponder exatamente à reserva registrada.
     */
    public function finish(int $sequence, string $nonce, string $digest, string $result): bool
    {
        if ($sequence <= 0 || ! preg_match('/\A[a-f0-9]{32}\z/D', $nonce)
            || ! preg_match('/\A[a-f0-9]{64}\z/D', $digest)
            || ! in_array($result, ['succeeded', 'failed'], true)) {
            return false;
        }

        try {
            return $this->transaction(function () use ($sequence, $nonce, $digest, $result): bool {
                $this->scope();
                $statement = $this->db->prepare('SELECT result FROM ledger_requests WHERE sequence = ? AND nonce = ? AND digest = ?');
                $statement->execute([$sequence, $nonce, $digest]);
                $current = $statement->fetchColumn();
                if ($current === $result) {
                    return true;
                }
                if ($current !== 'pending') {
                    return false;
                }

                $statement = $this->db->prepare('UPDATE ledger_requests SET result = ? WHERE sequence = ? AND nonce = ? AND digest = ?');
                $statement->execute([$result, $sequence, $nonce, $digest]);

                return $statement->rowCount() === 1;
            });
        } catch (Throwable) {
            return false;
        }
    }

    private function scope(): array
    {
        $row = $this->db->query('SELECT installation, last_sequence, used FROM ledger_scope WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if (! is_array($row) || $row['installation'] !== $this->installation
            || (int) $row['last_sequence'] < 0 || (int) $row['used'] < 0 || (int) $row['used'] > self::LIMIT) {
            throw new RuntimeException('Estado de manutenção indisponível.');
        }

        return $row;
    }

    private function transaction(callable $operation): mixed
    {
        // BEGIN IMMEDIATE serializa leitores que pretendem gravar, inclusive
        // quando dois consumidores recebem o mesmo pedido simultaneamente.
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $value = $operation();
            $this->db->exec('COMMIT');

            return $value;
        } catch (Throwable $exception) {
            try {
                $this->db->exec('ROLLBACK');
            } catch (Throwable) {
                // A reserva nunca é retornada ao consumidor se COMMIT falhar.
            }

            throw $exception;
        }
    }
}
