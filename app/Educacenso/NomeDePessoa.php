<?php

namespace App\Educacenso;

/**
 * Regra do Educacenso para nomes de pessoa sem CPF (registro 30, campos 6, 9
 * e 10, regra 1): até 100 caracteres alfabéticos, mais de uma palavra, mais
 * de um caractere em cada uma das duas primeiras palavras e no máximo quatro
 * caracteres iguais em sequência. Recebe o nome já normalizado (maiúsculas,
 * sem acentos), como vai no arquivo.
 */
final class NomeDePessoa
{
    /** @return list<string> */
    public static function problemas(string $nome): array
    {
        $problemas = [];
        if (mb_strlen($nome) > 100) {
            $problemas[] = 'deve ter no máximo 100 caracteres';
        }
        if (preg_match('/^[A-Z ]+$/', $nome) !== 1) {
            $problemas[] = 'deve ter só letras e espaços';
        }
        $palavras = preg_split('/ +/', trim($nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($palavras) < 2) {
            $problemas[] = 'deve ter nome e sobrenome';
        } elseif (mb_strlen($palavras[0]) < 2 || mb_strlen($palavras[1]) < 2) {
            $problemas[] = 'as duas primeiras palavras precisam ter mais de uma letra (abreviação como "M." não é aceita)';
        }
        if (preg_match('/(.)\1{4}/', $nome) === 1) {
            $problemas[] = 'não pode ter mais de quatro letras iguais seguidas';
        }

        return $problemas;
    }
}
