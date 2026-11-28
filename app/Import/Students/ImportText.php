<?php

namespace App\Import\Students;

use App\Address\BrazilianStates;
use App\Import\Xlsx\ExcelDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Regras de limpeza das planilhas que as escolas mandam.
 *
 * Nada aqui inventa dado: quando o valor nao e confiavel, devolve null e o
 * motivo, para a conferencia humana.
 */
final class ImportText
{
    private const NAME_PARTICLES = ['da', 'das', 'de', 'do', 'dos', 'e', 'di', 'du', 'del', 'van', 'von'];

    /** Chave de comparacao: sem acento, minusculas, espacos simples. */
    public static function key(?string $value): string
    {
        $value = self::clean($value) ?? '';
        $value = mb_strtolower($value, 'UTF-8');
        $ascii = \Normalizer::normalize($value, \Normalizer::FORM_D);
        $ascii = preg_replace('/\p{Mn}+/u', '', (string) $ascii) ?? $value;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '');
    }

    /** Remove espacos sobrando e caracteres invisiveis; texto vazio vira null. */
    public static function clean(mixed $value): ?string
    {
        if ($value === null || is_bool($value)) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        $text = is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value;
        $text = preg_replace('/[\x{00A0}\x{200B}\x{FEFF}\s]+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Nome de pessoa com capitalizacao de cadastro. Nomes ja escritos em
     * maiusculas e minusculas sao preservados; so CAIXA ALTA ou tudo minusculo
     * sao reescritos, mantendo os acentos.
     */
    public static function personName(mixed $value): ?string
    {
        $name = self::clean($value);
        if ($name === null) {
            return null;
        }

        $upper = mb_strtoupper($name, 'UTF-8');
        $lower = mb_strtolower($name, 'UTF-8');
        if ($name !== $upper && $name !== $lower) {
            return $name;
        }

        $words = explode(' ', $lower);
        foreach ($words as $index => $word) {
            if ($index > 0 && in_array($word, self::NAME_PARTICLES, true)) {
                continue;
            }

            $words[$index] = preg_replace_callback(
                "/(^|[-'’])(\p{Ll})/u",
                fn (array $m): string => $m[1].mb_strtoupper($m[2], 'UTF-8'),
                $word,
            ) ?? $word;
        }

        return implode(' ', $words);
    }

    /**
     * Data de nascimento a partir do que a escola escreveu.
     *
     * Aceita data do Excel, numero de serie do Excel, dd/mm/aaaa, dd-mm-aaaa,
     * dd.mm.aaaa, d/m/aa e aaaa-mm-dd. "8 anos" nao vira data: fica pendente,
     * com a idade guardada so como pista.
     *
     * @return array{date: ?DateTimeImmutable, issue: ?string, age: ?int}
     */
    public static function birthDate(mixed $value, DateTimeImmutable $reference): array
    {
        if ($value === null || $value === '') {
            return ['date' => null, 'issue' => 'Data de nascimento ausente.', 'age' => null];
        }

        $date = null;
        if ($value instanceof DateTimeInterface) {
            $date = DateTimeImmutable::createFromInterface($value)->setTime(0, 0);
        } elseif (is_int($value) || is_float($value)) {
            $date = ExcelDate::fromSerial((float) $value);
        } else {
            $text = self::clean($value) ?? '';
            if (preg_match('/^(\d{1,2})\s*anos?$/iu', $text, $age)) {
                return ['date' => null, 'issue' => 'A planilha traz so a idade ('.$text.'); a data de nascimento fica pendente.', 'age' => (int) $age[1]];
            }

            if (preg_match('/^\d{5}$/', $text)) {
                $date = ExcelDate::fromSerial((float) $text);
            } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
                $date = self::make((int) $m[1], (int) $m[2], (int) $m[3]);
            } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2}|\d{4})$/', $text, $m)) {
                $year = (int) $m[3];
                if (strlen($m[3]) === 2) {
                    // Ano com dois digitos: seculo atual se nao passar do ano de referencia.
                    $century = intdiv((int) $reference->format('Y'), 100) * 100;
                    $year = $century + $year > (int) $reference->format('Y') ? $century - 100 + $year : $century + $year;
                }
                $date = self::make($year, (int) $m[2], (int) $m[1]);
            }
        }

        if ($date === null) {
            return ['date' => null, 'issue' => 'Data de nascimento ilegivel: "'.(self::clean($value) ?? '').'".', 'age' => null];
        }

        // Nascimento representa uma data civil, não um instante no tempo.
        // Preserva o dia da fonte e compara com o dia civil de referência.
        $date = new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC'));
        if ($date->format('Y-m-d') > $reference->format('Y-m-d') || (int) $date->format('Y') < 1900) {
            return ['date' => null, 'issue' => 'Data de nascimento fora do intervalo possivel: '.$date->format('d/m/Y').'.', 'age' => null];
        }

        return ['date' => $date, 'issue' => null, 'age' => null];
    }

    private static function make(int $year, int $month, int $day): ?DateTimeImmutable
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
    }

    /** Sexo como o Censo Escolar registra: "female" ou "male"; o resto fica pendente. */
    public static function sex(mixed $value): ?string
    {
        return match (self::key(self::clean($value))) {
            'f', 'fem', 'feminino', 'menina', 'mulher' => 'female',
            'm', 'masc', 'masculino', 'menino', 'homem' => 'male',
            default => null,
        };
    }

    /** Cor/raça nas categorias do IBGE usadas pelo Censo Escolar. */
    public static function raceColor(mixed $value): ?string
    {
        return match (self::key(self::clean($value))) {
            'branca', 'branco' => 'branca',
            'preta', 'preto' => 'preta',
            'parda', 'pardo' => 'parda',
            'amarela', 'amarelo' => 'amarela',
            'indigena' => 'indigena',
            'nao declarada', 'nao declarado', 'nd', 'n d' => 'nao_declarada',
            default => null,
        };
    }

    public static function nationality(mixed $value): ?string
    {
        return match (self::key(self::clean($value))) {
            'brasileira', 'brasileiro', 'brasil', 'br' => 'brasileira',
            'naturalizada', 'naturalizado', 'brasileira naturalizada', 'brasileiro naturalizado',
            'brasileira nascida no exterior ou naturalizada', 'brasileiro nascido no exterior ou naturalizado',
            'nascida no exterior', 'nascido no exterior' => 'naturalizada',
            'estrangeira', 'estrangeiro' => 'estrangeira',
            default => null,
        };
    }

    /**
     * Naturalidade "Recife/PE", "Recife - PE", "Recife (PE)" ou "Recife, PE".
     * A UF só é aceita se for uma sigla válida; a cidade sem UF continua pendente.
     *
     * @return array{city: ?string, state: ?string}
     */
    public static function birthplace(mixed $value): array
    {
        $text = self::clean($value);
        if ($text === null) {
            return ['city' => null, 'state' => null];
        }

        $state = null;
        if (preg_match('/\A(.+?)\s*(?:\/|-|,|\()\s*([A-Za-z]{2})\)?\z/u', $text, $m) === 1
            && isset(BrazilianStates::NAMES[strtoupper($m[2])])) {
            $text = $m[1];
            $state = strtoupper($m[2]);
        }

        $city = self::personName(trim($text, " \t-/,"));
        if ($city === null || mb_strlen($city) > 120 || preg_match('/\A\p{L}[\p{L}\p{M}\' .-]*\z/u', $city) !== 1) {
            return ['city' => null, 'state' => $state];
        }

        return ['city' => $city, 'state' => $state];
    }

    /** Telefone com DDD: 10 ou 11 dígitos (com ou sem 55 na frente). Sem DDD, null. */
    public static function phone(mixed $value): ?string
    {
        $text = self::clean($value);
        if ($text === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return in_array(strlen($digits), [10, 11], true) ? $digits : null;
    }

    /** RA da SED: digitos, digito verificador e UF. */
    public static function ra(mixed $value): ?string
    {
        $text = self::clean($value);
        if ($text === null) {
            return null;
        }

        $text = strtoupper(str_replace(' ', '', $text));

        return preg_match('/\A\d{9,12}-?[\dX]\/[A-Z]{2}\z/', $text) === 1 && isset(BrazilianStates::NAMES[substr($text, -2)]) ? $text : null;
    }

    /** CEP brasileiro, sem adivinhar zero à esquerda perdido em célula numérica. */
    public static function postalCode(mixed $value): ?string
    {
        $text = self::clean($value);
        if ($text === null || preg_match('/\A\d{5}-?\d{3}\z/', $text) !== 1) {
            return null;
        }

        $digits = str_replace('-', '', $text);

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }

    /** Zona de residência só é gravada quando a planilha informa claramente. */
    public static function residenceZone(mixed $value): ?string
    {
        return match (self::key(self::clean($value))) {
            'urbana', 'urbano', 'urban', 'zona urbana' => 'urban',
            'rural', 'zona rural' => 'rural',
            default => null,
        };
    }

    /**
     * Turma como a escola escreve ("1º A", "1A", "1° ano A", "1º ANO - A",
     * "MII-A") reduzida a uma chave comparavel ("1A", "MIIA").
     */
    public static function classKey(?string $label): string
    {
        $key = strtoupper(self::key($label));
        $key = preg_replace('/\b(ANO|ANOS|SERIE|TURMA)\b/', ' ', $key) ?? $key;

        return preg_replace('/[^A-Z0-9]/', '', $key) ?? '';
    }

    /** "S", "X", "Sim" = usa; vazio = nao usa (quando a coluna existe). */
    public static function yesNo(mixed $value): bool
    {
        $key = self::key(self::clean($value));

        return in_array($key, ['s', 'sim', 'x', 'yes', '1', 'ok'], true);
    }

    /**
     * Endereco numa coluna so: "R. do Sol Nascente, 1491 - Vila Nova Serena".
     *
     * @return array{street: ?string, number: ?string, district: ?string}
     */
    public static function address(mixed $value): array
    {
        $text = self::clean($value);
        if ($text === null) {
            return ['street' => null, 'number' => null, 'district' => null];
        }

        $district = null;
        if (preg_match('/^(.*)\s+-\s+([^-]+)$/u', $text, $m)) {
            $text = trim($m[1]);
            $district = trim($m[2]);
        }

        $number = null;
        if (preg_match('/^(.*),\s*([^,]+)$/u', $text, $m)) {
            $text = trim($m[1]);
            $number = trim($m[2]);
        }

        $abbreviations = [
            'r' => 'Rua', 'av' => 'Avenida', 'trav' => 'Travessa', 'tv' => 'Travessa',
            'est' => 'Estrada', 'estr' => 'Estrada', 'al' => 'Alameda', 'pca' => 'Praça', 'pc' => 'Praça', 'rod' => 'Rodovia',
        ];
        if (preg_match('/^([A-Za-zçÇ]+)\.\s*(.+)$/u', $text, $m) && isset($abbreviations[self::key($m[1])])) {
            $text = $abbreviations[self::key($m[1])].' '.$m[2];
        }

        return ['street' => $text === '' ? null : $text, 'number' => $number, 'district' => $district];
    }
}
