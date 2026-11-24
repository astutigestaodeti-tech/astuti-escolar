<?php

namespace App\Educacenso;

use App\Models\Tenant\CurricularComponent;
use App\Models\Tenant\SchoolClass;
use Illuminate\Support\Str;

/**
 * Sugestões para começar o preenchimento do Censo da turma, a partir do que já
 * está cadastrado. Nada é gravado sem a escola conferir e salvar: na dúvida,
 * não sugere.
 */
final class SugestaoDeTurma
{
    /** Ano do ensino fundamental de 9 anos -> código da etapa na Tabela de Etapas. */
    private const ANOS = [1 => 14, 2 => 15, 3 => 16, 4 => 17, 5 => 18, 6 => 19, 7 => 20, 8 => 21, 9 => 41];

    /** Nome do componente curricular -> código da área do conhecimento do Inep. */
    private const COMPONENTES = [
        'lingua portuguesa' => 6, 'portugues' => 6, 'matematica' => 3, 'ciencias' => 5,
        'historia' => 12, 'geografia' => 13, 'arte' => 10, 'artes' => 10, 'educacao fisica' => 11,
        'lingua inglesa' => 7, 'ingles' => 7, 'espanhol' => 8, 'lingua espanhola' => 8,
        'ensino religioso' => 26, 'filosofia' => 14, 'sociologia' => 29, 'libras' => 23,
        'informatica' => 16, 'computacao' => 16,
    ];

    public static function etapa(SchoolClass $turma): ?int
    {
        $texto = ' '.Str::lower(Str::ascii($turma->stage.' '.$turma->name)).' ';

        if (preg_match('/\beja\b|jovens e adultos/', $texto)) {
            return match (true) {
                str_contains($texto, 'multi') => 72,
                str_contains($texto, 'anos finais') || str_contains($texto, '2o segmento') || str_contains($texto, '2 segmento') => 70,
                str_contains($texto, 'anos iniciais') || str_contains($texto, '1o segmento') || str_contains($texto, '1 segmento') => 69,
                default => null,
            };
        }
        if (str_contains($texto, 'multietapa') || (str_contains($texto, 'infantil') && str_contains($texto, 'fundamental'))) {
            return 56;
        }
        if (str_contains($texto, 'correcao de fluxo')) {
            return 23;
        }
        if (preg_match('/multisseriad|\bmulti\b/', $texto)) {
            return 22;
        }
        if (preg_match('/bercario|creche|maternal/', $texto)) {
            return 1;
        }
        if (preg_match('/\bpre\b|pre-escola|pre escola|jardim/', $texto)) {
            return 2;
        }
        if (preg_match_all('/\b([1-9])\s*(?:o|º|°)?\s*ano\b/u', $texto, $m) === 1) {
            return self::ANOS[(int) $m[1][0]];
        }

        return null;
    }

    public static function tipo(SchoolClass $turma): ?int
    {
        $texto = Str::lower(Str::ascii($turma->stage.' '.$turma->name));
        if (preg_match('/\baee\b|sala de recursos|atendimento educacional especializado/', $texto)) {
            return 5;
        }

        return self::etapa($turma) !== null ? 6 : null;
    }

    /** @return list<int> áreas dos componentes ativos da rede permitidas para a etapa */
    public static function areas(int $etapa, Tabelas $tabelas): array
    {
        $permitidas = $tabelas->areasPermitidas($etapa);
        $areas = [];
        foreach (CurricularComponent::query()->where('active', true)->pluck('name') as $nome) {
            $codigo = self::COMPONENTES[Str::lower(Str::ascii(trim((string) $nome)))] ?? null;
            if ($codigo !== null && in_array($codigo, $permitidas, true)) {
                $areas[] = $codigo;
            }
        }
        sort($areas);

        return array_values(array_unique($areas));
    }
}
