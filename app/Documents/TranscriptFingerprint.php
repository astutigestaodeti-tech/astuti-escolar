<?php

namespace App\Documents;

class TranscriptFingerprint
{
    /**
     * SHA-256 canônico: a ordem das chaves JSONB pode mudar ao salvar no
     * PostgreSQL, mas os valores e a ordem das linhas precisam permanecer iguais.
     */
    public static function fromSnapshot(array $snapshot): string
    {
        return hash('sha256', json_encode(
            self::canonical($snapshot),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonical($item);
        }

        return $value;
    }
}
