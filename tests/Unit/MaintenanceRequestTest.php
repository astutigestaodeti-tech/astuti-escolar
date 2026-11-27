<?php

namespace Tests\Unit;

use App\Developer\MaintenanceRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaintenanceRequestTest extends TestCase
{
    private const NOW = 1795720000;

    private function payload(array $overrides = []): string
    {
        return json_encode(array_replace([
            'schema' => 1,
            'installation' => 'monte-sereno',
            'sequence' => 8,
            'nonce' => str_repeat('a', 32),
            'issued_at' => self::NOW - 10,
            'expires_at' => self::NOW + 60,
            'operation' => 'diagnostico.saude',
        ], $overrides), JSON_THROW_ON_ERROR);
    }

    private function signed(string $payload): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            bin2hex(sodium_crypto_sign_detached(MaintenanceRequest::DOMAIN.$payload, sodium_crypto_sign_secretkey($pair))),
            bin2hex(sodium_crypto_sign_publickey($pair)),
        ];
    }

    public function test_aceita_somente_diagnosticos_assinados_para_a_instalacao(): void
    {
        foreach (['diagnostico.saude', 'diagnostico.implantacao'] as $operation) {
            $payload = $this->payload(['operation' => $operation]);
            [$signature, $key] = $this->signed($payload);
            $validator = new MaintenanceRequest;
            $this->assertSame(json_decode($payload, true), $validator->validate($payload, $signature, $key, 'monte-sereno', 7, self::NOW));
            $this->assertNull($validator->validate($payload, $signature, $key, 'monte-sereno', 8, self::NOW));
            $this->assertNull($validator->validate($payload, $signature, $key, 'outra-instalacao', 7, self::NOW));
            $this->assertNull($validator->validate($payload, $signature, $key, 'monte-sereno', -1, self::NOW));
        }
    }

    public static function invalidRequests(): array
    {
        return [
            'schema desconhecido' => [['schema' => 2]],
            'sequencia texto' => [['sequence' => '8']],
            'sequencia anterior' => [['sequence' => 6]],
            'nonce invalido' => [['nonce' => 'repeticao']],
            'expirado' => [['expires_at' => self::NOW]],
            'emitido no futuro' => [['issued_at' => self::NOW + 1]],
            'validade excessiva' => [['expires_at' => self::NOW + 301]],
            'comando arbitrario' => [['operation' => 'shell']],
            'operacao ainda nao autorizada' => [['operation' => 'deploy']],
            'argumentos extras' => [['arguments' => ['command' => 'id']]],
            'escopo diferente' => [['installation' => 'outra-instalacao']],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function test_rejeita_pedido_invalido_mesmo_com_assinatura_valida(array $overrides): void
    {
        $payload = $this->payload($overrides);
        [$signature, $key] = $this->signed($payload);
        $this->assertNull((new MaintenanceRequest)->validate($payload, $signature, $key, 'monte-sereno', 7, self::NOW));
    }

    public function test_rejeita_adulteracao_chave_errada_e_formato_nao_canonico(): void
    {
        $payload = $this->payload();
        [$signature, $key] = $this->signed($payload);
        [, $otherKey] = $this->signed($payload);
        $validator = new MaintenanceRequest;
        $this->assertNull($validator->validate($this->payload(['sequence' => 9]), $signature, $key, 'monte-sereno', 7, self::NOW));
        $this->assertNull($validator->validate($payload, $signature, $otherKey, 'monte-sereno', 7, self::NOW));
        $this->assertNull($validator->validate($payload, 'invalida', $key, 'monte-sereno', 7, self::NOW));
        foreach ([' '.$payload, str_replace('"schema":1', '"schema":1,"schema":1', $payload), str_repeat('x', 4097), '{'] as $invalid) {
            [$invalidSignature, $invalidKey] = $this->signed($invalid);
            $this->assertNull($validator->validate($invalid, $invalidSignature, $invalidKey, 'monte-sereno', 7, self::NOW));
        }
    }
}
