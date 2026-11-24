<?php

namespace App\Educacenso;

/**
 * Conferência de um valor contra o que a planilha do Inep diz do campo:
 * obrigatoriedade, tamanho, tipo, opções e caracteres permitidos.
 *
 * Regras que dependem de outro campo ou registro ("deve ser nulo quando…")
 * ficam nas regras condicionais de cada registro, não aqui.
 */
final class ValidadorDeCampo
{
    /** Regra de e-mail transcrita do leiaute (registro 00, campo 17; registro 30, campo 110). */
    private const EMAIL = '/^[-A-Z0-9~!$%^&*_=+}{\'?]+(\.[-A-Z0-9~!$%^&*_=+}{\'?]+)*@([A-Z0-9_][-A-Z0-9_]*(\.[-A-Z0-9_]+)*\.(AERO|ARPA|BIZ|COM|COOP|EDU|GOV|INFO|INT|MIL|MUSEUM|NAME|NET|ORG|PRO|TRAVEL|MOBI|[A-Z][A-Z])|([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}))(:[0-9]{1,5})?$/i';

    /**
     * @param  string|null  $valor  já no formato do arquivo (use Texto::normalizar antes)
     * @return list<string> problemas encontrados; vazio quando o valor é aceito
     */
    public static function conferir(Campo $campo, ?string $valor): array
    {
        $valor = $valor === '' ? null : $valor;

        if ($campo->somenteExportacao()) {
            return $valor === null ? [] : ['deve ir vazio: o Inep só preenche este campo no arquivo que devolve'];
        }
        if ($valor === null) {
            return $campo->obrigatorio === 's' ? ['é obrigatório'] : [];
        }
        if (str_contains($valor, '|') || preg_match('/[\r\n]/', $valor) === 1) {
            return ['contém separador ou quebra de linha'];
        }

        $problemas = [];
        $tamanho = mb_strlen($valor);
        if ($campo->tamanho !== null && $campo->tamanho > 0) {
            if ($campo->tamanhoFixo && $tamanho !== $campo->tamanho) {
                $problemas[] = "deve ter exatamente {$campo->tamanho} caracteres";
            } elseif (! $campo->tamanhoFixo && $tamanho > $campo->tamanho) {
                $problemas[] = "deve ter no máximo {$campo->tamanho} caracteres";
            }
        }
        if ($valor !== mb_strtoupper($valor) || preg_match('/\p{Mn}|[À-ÖØ-öø-ÿ]/u', $valor) === 1) {
            $problemas[] = 'não pode ter letras minúsculas nem acentos';
        }

        $problema = match ($campo->tipo) {
            'num' => preg_match('/^\d+$/', $valor) === 1 ? null : 'deve ter só números',
            'data' => self::data($valor) ? null : 'deve ser uma data válida no formato dd/mm/aaaa',
            'hora' => self::hora($valor),
            'email' => preg_match(self::EMAIL, $valor) === 1 ? null : 'não é um e-mail aceito pela regra do Inep',
            default => null,
        };
        if ($problema !== null) {
            $problemas[] = $problema;
        }
        if ($campo->opcoes !== null && ! in_array($valor, $campo->opcoes, true)) {
            $problemas[] = 'deve ser um destes códigos: '.implode(', ', $campo->opcoes);
        }

        $invalidos = self::foraDoConjunto($campo, $valor);
        if ($invalidos !== []) {
            $problemas[] = 'tem caractere não aceito pelo Educacenso: '.implode(' ', array_map(fn (string $c): string => "«{$c}»", $invalidos));
        }

        return array_values(array_unique($problemas));
    }

    private static function data(string $valor): bool
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[1], (int) $m[3]);
    }

    /** Regras 2 e 3 dos campos de horário do registro 20 (campos 7 a 13). */
    private static function hora(string $valor): ?string
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5][05])-([01]\d|2[0-3]):([0-5][05])$/', $valor, $m) !== 1) {
            return 'deve estar no formato hh:mm-hh:mm, com horas de 00 a 23 e minutos múltiplos de 5';
        }

        return ((int) $m[1] * 60 + (int) $m[2]) < ((int) $m[3] * 60 + (int) $m[4])
            ? null
            : 'o horário inicial deve ser menor que o final';
    }

    /**
     * Caracteres fora do conjunto da coluna "valores permitidos" ("A-Z 0-9 ª º -").
     * Nomes, endereços e nomes de turma aceitam espaço entre palavras: as regras
     * pedem "mais de uma palavra" e o exemplo oficial do Inep usa "JOAO DA SILVA".
     *
     * @return list<string>
     */
    private static function foraDoConjunto(Campo $campo, string $valor): array
    {
        $conjunto = self::conjunto($campo);
        if ($conjunto === null) {
            return [];
        }
        $fora = [];
        foreach (mb_str_split($valor) as $caractere) {
            if (! in_array($caractere, $conjunto, true)) {
                $fora[] = $caractere === ' ' ? 'espaço' : $caractere;
            }
        }

        return array_values(array_unique($fora));
    }

    /** @return list<string>|null */
    private static function conjunto(Campo $campo): ?array
    {
        if (! in_array($campo->tipo, ['alfa', 'num', 'email'], true) || $campo->permitidos === null) {
            return null;
        }
        $partes = preg_split('/\s+/u', trim($campo->permitidos));
        $conjunto = [];
        foreach ($partes as $parte) {
            if ($parte === 'A-Z') {
                $conjunto = [...$conjunto, ...range('A', 'Z')];
            } elseif ($parte === '0-9') {
                $conjunto = [...$conjunto, ...array_map('strval', range(0, 9))];
            } elseif (mb_strlen($parte) === 1) {
                $conjunto[] = $parte;
            } else {
                return null; // remete a tabela auxiliar ou lista de opções: conferido em outro lugar
            }
        }
        if ($campo->tipo === 'alfa' && in_array('A', $conjunto, true)
            && ! str_starts_with($campo->nome, 'Código') && ! str_starts_with($campo->nome, 'Número')) {
            $conjunto[] = ' ';
        }

        return $conjunto;
    }
}
