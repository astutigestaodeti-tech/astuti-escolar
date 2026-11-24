<?php

namespace App\Academics;

use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\Student;
use App\Waitlist\StageCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class RenewalStage
{
    // Índices internos da sequência, não códigos do Inep.
    public const LABELS = [
        -4 => 'Berçário', -3 => 'Maternal I', -2 => 'Maternal II', -1 => 'Pré I', 0 => 'Pré II',
        1 => '1º ano', 2 => '2º ano', 3 => '3º ano', 4 => '4º ano', 5 => '5º ano',
        6 => '6º ano', 7 => '7º ano', 8 => '8º ano', 9 => '9º ano',
    ];

    public const INEP = [
        1 => 'Creche', 2 => 'Pré-escola', 14 => '1º ano', 15 => '2º ano', 16 => '3º ano',
        17 => '4º ano', 18 => '5º ano', 19 => '6º ano', 20 => '7º ano', 21 => '8º ano', 41 => '9º ano',
    ];

    public static function normalized(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }

    public static function isInfant(SchoolClass $class): bool
    {
        $stage = self::normalized($class->stage);

        return ! str_contains($stage, 'fundamental') && preg_match('/infantil|creche|pre[- ]escola|maternal|bercario|\bpre[- ]*(?:i|ii|1|2)\b/', $stage) === 1;
    }

    public static function isMulti(SchoolClass $class): bool
    {
        return preg_match('/\bmulti\b|multisseriad|multiseriad|multiano|multietapa/', self::text($class)) === 1;
    }

    public static function supported(SchoolClass $class): bool
    {
        return ! preg_match('/\beja\b|jovens e adultos|correcao de fluxo/', self::text($class))
            && ! preg_match('/ensino medio|educacao profissional/', self::text($class))
            && (self::isInfant($class) || str_contains(self::normalized($class->stage), 'fundamental') || self::isMulti($class));
    }

    /** Turmas multi só recebem sugestões para séries explicitamente atendidas. */
    public static function levels(SchoolClass $class): array
    {
        if (! self::supported($class)) {
            return [];
        }

        if (self::isMulti($class)) {
            $text = self::text($class);
            $levels = [];
            // Ex.: "1º ao 3º ano", "1º e 2º ano", "1º, 2º e 3º ano".
            $ord = '[1-9]\s*(?:o|a|º|ª|°)?';
            if (preg_match_all('/('.$ord.')\s*(?:ao|a|ate|-)\s*('.$ord.')\s*(?:anos?|series?)\b/u', $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $levels = [...$levels, ...range((int) $match[1], (int) $match[2])];
                }
            }
            if (preg_match_all('/\b([1-9])\s*(?:o|a|º|ª|°)(?:\s|,|$)/u', $text, $matches)) {
                $levels = [...$levels, ...array_map('intval', $matches[1])];
            }
            if (preg_match_all('/\b([1-9])\s*(?:o|a|º|ª|°)?\s*(?:anos?|series?)\b/u', $text, $matches)) {
                $levels = [...$levels, ...array_map('intval', $matches[1])];
            }
            foreach ([-4, -3, -2, -1, 0] as $level) {
                if (self::infantLevel(self::normalized(self::LABELS[$level]), $text)) {
                    $levels[] = $level;
                }
            }

            if ($levels === [] && preg_match('/anos iniciais/', $text)) {
                $levels = range(1, 5);
            } elseif ($levels === [] && preg_match('/anos finais/', $text)) {
                $levels = range(6, 9);
            }

            return array_values(array_unique($levels));
        }

        if (self::isInfant($class)) {
            $text = self::normalized($class->name).' '.self::normalized($class->stage);
            $levels = array_values(array_filter([-4, -3, -2, -1, 0], fn (int $level): bool => self::infantLevel(self::normalized(self::LABELS[$level]), $text)));

            return count($levels) === 1 ? $levels : [];
        }

        $grade = EnrollmentRenewal::grade($class);

        return $grade === null ? [] : [$grade];
    }

    private static function infantLevel(string $label, string $text): bool
    {
        $pattern = match ($label) {
            'bercario' => 'bercario',
            'maternal i' => 'maternal\s*(?:i|1)',
            'maternal ii' => 'maternal\s*(?:ii|2)',
            'pre i' => '(?:pre[- ]*\s*(?:i|1)|pi)',
            'pre ii' => '(?:pre[- ]*\s*(?:ii|2)|pii)',
        };

        return preg_match('/\b'.$pattern.'\b/u', $text) === 1;
    }

    public static function inep(int $level): int
    {
        return match (true) {
            $level <= -2 => 1,
            $level <= 0 => 2,
            $level === 9 => 41,
            default => $level + 13,
        };
    }

    public static function allowsCode(SchoolClass $class, int $code): bool
    {
        if (! self::isMulti($class) || ! self::supported($class) || ! isset(self::INEP[$code])) {
            return false;
        }
        $levels = self::levels($class);
        if ($levels !== []) {
            return in_array($code, array_map(self::inep(...), $levels), true);
        }

        if (self::isInfant($class)) {
            return in_array($code, [1, 2], true);
        }
        $stage = self::normalized($class->stage);
        if (str_contains($stage, 'fundamental') && ! str_contains($stage, 'infantil') && ! str_contains($stage, 'multietapa')) {
            return $code >= 14;
        }

        return true;
    }

    public static function fromInep(int $code, Student $student, int $year): ?int
    {
        if ($code === 1 || $code === 2) {
            $age = self::age($student, $year);
            $level = match ($age) {
                0, 1 => -4,
                2 => -3,
                3 => -2,
                4 => -1,
                5 => 0,
                default => null,
            };

            return $level !== null && self::inep($level) === $code ? $level : null;
        }

        return $code === 41 ? 9 : (in_array($code, range(14, 21), true) ? $code - 13 : null);
    }

    public static function age(Student $student, int $year): ?int
    {
        if ($student->birth_date === null) {
            return null;
        }

        // A mesma regra de corte da lista de espera, aplicada ao ano avaliado.
        $cutoff = app(StageCalculator::class)->cutoff()->setYear($year);
        $birth = CarbonImmutable::parse($student->birth_date->toDateString());

        return $birth->gt($cutoff) ? null : (int) floor($birth->diffInYears($cutoff));
    }

    public static function next(int $level, string $result, Student $student, int $year): ?int
    {
        if ($level > 0) {
            return $level + ($result === 'retido' ? 0 : 1);
        }
        if ($result === 'retido') {
            return null;
        }

        $age = self::age($student, $year);
        if ($level === -4 && $age !== null && $age <= 1) {
            return -4;
        }
        $next = $level + 1;

        return $age !== null && ($next === 1 ? $age >= 6 : $age === $next + 5) ? $next : null;
    }

    /** Histórico textual apenas quando identifica uma série sem ambiguidade. */
    public static function historicalLevel(string $stage): ?int
    {
        $text = self::normalized($stage);
        if (preg_match('/multi|\beja\b|jovens e adultos|medio/', $text)) {
            return null;
        }
        if (preg_match_all('/\b([1-9])\s*(?:o|a|º|ª|°)?\s*(?:ano|serie)\b/u', $text, $matches) === 1) {
            return (int) $matches[1][0];
        }
        foreach ([-4, -3, -2, -1, 0] as $level) {
            if (self::infantLevel(self::normalized(self::LABELS[$level]), $text)) {
                return $level;
            }
        }

        return null;
    }

    private static function text(SchoolClass $class): string
    {
        return self::normalized($class->stage.' '.$class->name.' '.$class->modality);
    }
}
