<?php

namespace App\Developer;

/**
 * Validador offline. Não executa operações nem registra consumo de sequência.
 * O futuro consumidor deve reservar a sequência sob lock durável antes de agir.
 */
final class MaintenanceRequest
{
    public const DOMAIN = "astuti-maintenance:v1\n";

    private const MAX_BYTES = 4096;

    private const MAX_TTL = 300;

    private const OPERATIONS = ['diagnostico.saude', 'diagnostico.implantacao'];

    /**
     * Recebe a chave pública e a instalação de configuração local confiável,
     * nunca do pedido. A sequência é a última reservada pelo consumidor.
     *
     * @return array{schema:int,installation:string,sequence:int,nonce:string,issued_at:int,expires_at:int,operation:string}|null
     */
    public function validate(string $payload, string $signatureHex, string $publicKeyHex, string $installation, int $lastSequence, int $now): ?array
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')
            || strlen($payload) > self::MAX_BYTES
            || ! preg_match('/\A[a-f0-9]{128}\z/D', $signatureHex)
            || ! preg_match('/\A[a-f0-9]{64}\z/D', $publicKeyHex)
            || ! preg_match('/\A[a-z0-9][a-z0-9-]{0,62}\z/D', $installation)
            || $lastSequence < 0 || $now <= 0) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached(hex2bin($signatureHex), self::DOMAIN.$payload, hex2bin($publicKeyHex))) {
            return null;
        }

        $data = json_decode($payload, true, 4);
        if (! is_array($data)
            || array_keys($data) !== ['schema', 'installation', 'sequence', 'nonce', 'issued_at', 'expires_at', 'operation']
            || json_encode($data) !== $payload
            || $data['schema'] !== 1
            || $data['installation'] !== $installation
            || ! is_int($data['sequence']) || $data['sequence'] <= $lastSequence
            || ! is_string($data['nonce']) || ! preg_match('/\A[a-f0-9]{32}\z/D', $data['nonce'])
            || ! is_int($data['issued_at']) || $data['issued_at'] <= 0 || $data['issued_at'] > $now
            || ! is_int($data['expires_at']) || $data['expires_at'] <= $now
            || $data['expires_at'] - $data['issued_at'] > self::MAX_TTL
            || ! in_array($data['operation'], self::OPERATIONS, true)) {
            return null;
        }

        return $data;
    }
}
