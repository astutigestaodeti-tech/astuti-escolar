<?php

namespace App\Security;

use InvalidArgumentException;

/** RFC 6238 (HMAC-SHA1, 30 segundos, 6 dígitos), interoperável com apps TOTP. */
class Totp
{
    public function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bytes = random_bytes(20);
        $buffer = 0;
        $bits = 0;
        $result = '';

        foreach (unpack('C*', $bytes) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($buffer >> $bits) & 31];
            }
        }

        return $result;
    }

    public function at(string $secret, int $timestamp): string
    {
        $bytes = $this->decode($secret);
        $step = intdiv($timestamp, 30);
        $message = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
        $digest = hash_hmac('sha1', $message, $bytes, true);
        $offset = ord($digest[19]) & 15;
        $value = ((ord($digest[$offset]) & 127) << 24)
            | (ord($digest[$offset + 1]) << 16)
            | (ord($digest[$offset + 2]) << 8)
            | ord($digest[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, int $timestamp): bool
    {
        return $this->matchingStep($secret, $code, $timestamp) !== null;
    }

    public function matchingStep(string $secret, string $code, int $timestamp): ?int
    {
        if (preg_match('/\A[0-9]{6}\z/D', $code) !== 1) {
            return null;
        }

        $currentStep = intdiv($timestamp, 30);

        foreach ([-1, 0, 1] as $shift) {
            $step = $currentStep + $shift;
            if ($step >= 0 && hash_equals($this->at($secret, $step * 30), $code)) {
                return $step;
            }
        }

        return null;
    }

    private function decode(string $secret): string
    {
        if (preg_match('/\A[A-Z2-7]{32}\z/D', $secret) !== 1) {
            throw new InvalidArgumentException('Segredo de autenticação inválido.');
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $result = '';

        foreach (str_split($secret) as $char) {
            $buffer = ($buffer << 5) | strpos($alphabet, $char);
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 255);
            }
        }

        return $result;
    }
}
