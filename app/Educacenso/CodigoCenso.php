<?php

namespace App\Educacenso;

/**
 * Código "no sistema próprio" de turma e pessoa (registros 20, 30, 40, 50 e 60):
 * 20 caracteres A-Z e 0-9. Tem exatamente 20 para atender às duas leituras do
 * registro 20, campo 3 (tamanho fixo 20 e regra "até 20 caracteres").
 */
final class CodigoCenso
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function novo(): string
    {
        $codigo = '';
        for ($i = 0; $i < 20; $i++) {
            $codigo .= self::ALFABETO[random_int(0, 35)];
        }

        return $codigo;
    }

    /**
     * Código estável de uma pessoa a partir do seu identificador interno (UUID):
     * os 20 primeiros dígitos hexadecimais, em maiúsculas. Não muda entre os
     * anos, como o Inep espera para casar o retorno com o cadastro.
     */
    public static function dePessoa(string $uuid): string
    {
        return strtoupper(substr(str_replace('-', '', $uuid), 0, 20));
    }
}
