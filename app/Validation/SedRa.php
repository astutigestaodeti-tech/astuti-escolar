<?php

namespace App\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RA da Secretaria Escolar Digital (SED/SP): 9 a 12 números, hífen, dígito
 * verificador (número ou X), barra e sigla do estado. Ex.: 000123456789-0/SP.
 *
 * Em vez de só dizer "formato inválido", explica o que está fora do padrão.
 */
class SedRa implements ValidationRule
{
    public const EXAMPLE = '000123456789-0/SP';

    private const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB',
        'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $problem = is_string($value) ? self::problem($value) : 'use só números, o hífen, a barra e a sigla do estado';
        if ($problem !== null) {
            $fail('RA fora do padrão da SED: '.$problem.'. Exemplo: '.self::EXAMPLE.'.');
        }
    }

    /** Devolve o que está errado no RA, ou null se ele segue o padrão. */
    public static function problem(string $ra): ?string
    {
        $ra = strtoupper(preg_replace('/\s+/', '', $ra) ?? '');

        if (preg_match('/\A(\d*)(-([0-9X]*))?(\/([A-Z]*))?\z/', $ra, $parts) !== 1) {
            return 'use só números, o hífen, a barra e a sigla do estado, nessa ordem';
        }

        $digits = strlen($parts[1]);
        $hasCheck = ($parts[2] ?? '') !== '';
        $check = $parts[3] ?? '';
        $hasUf = ($parts[4] ?? '') !== '';
        $uf = $parts[5] ?? '';

        $problems = [];
        if ($digits === 0) {
            $problems[] = 'faltam os números do RA';
        } elseif ($digits < 9) {
            $problems[] = 'tem só '.$digits.($digits === 1 ? ' número' : ' números').' antes do hífen (são de 9 a 12)';
        } elseif ($digits > 12) {
            $problems[] = 'tem '.$digits.' números antes do hífen (o máximo é 12)';
        }

        if (! $hasCheck || $check === '') {
            $problems[] = 'falta o dígito verificador depois do hífen';
        } elseif (strlen($check) > 1) {
            $problems[] = 'o dígito verificador depois do hífen é um só (número ou X)';
        }

        if (! $hasUf || $uf === '') {
            $problems[] = 'falta a barra com a sigla do estado (/SP)';
        } elseif (strlen($uf) !== 2 || ! in_array($uf, self::UFS, true)) {
            $problems[] = '"'.$uf.'" não é sigla de estado';
        }

        if ($problems === []) {
            return null;
        }

        if (count($problems) === 1) {
            return $problems[0];
        }

        $last = array_pop($problems);

        return implode(', ', $problems).' e '.$last;
    }
}
