<?php

namespace App\Students;

use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cadastros repetidos do mesmo aluno: mesmo nome (sem acento, caixa ou espaço extra)
 * e nascimento igual ou em branco. Decide qual cadastro fica (o que tem mais registros
 * e dados) e quais cópias podem ser arquivadas com segurança: só as que não têm nenhum
 * registro próprio em outra tabela e cujas matrículas repetem as do cadastro que fica.
 */
class DuplicateStudents
{
    /** SQL da chave de comparação do nome, igual à da pendência "Possível cadastro repetido". */
    public const NAME_KEY = "regexp_replace(public.f_unaccent(lower(btrim(full_name))), '\\s+', ' ', 'g')";

    /** Mesmo nome e nascimento compatível (igual ou em branco em um dos dois). */
    public static function exists(string $name, ?string $birthDate, ?string $exceptId = null): bool
    {
        return Student::query()
            ->whereRaw(self::NAME_KEY." = regexp_replace(public.f_unaccent(lower(btrim(?))), '\\s+', ' ', 'g')", [$name])
            ->when($birthDate !== null, fn ($query) => $query->where(fn ($q) => $q->whereNull('birth_date')->orWhereDate('birth_date', $birthDate)))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * Trava curta por nome, dentro da transação: dois envios iguais (clique duplo,
     * duas abas) esperam um pelo outro e o segundo encontra o primeiro.
     */
    public static function lock(string $name): void
    {
        DB::connection('tenant')->select(
            "SELECT pg_advisory_xact_lock(hashtextextended('astuti-aluno:' || regexp_replace(public.f_unaccent(lower(btrim(?))), '\\s+', ' ', 'g'), 0))",
            [$name],
        );
    }

    /**
     * @return list<array{name: string, keeper: string, copies: list<array{id: string, blocked: ?string}>}>
     */
    public function groups(): array
    {
        $students = Student::query()
            ->selectRaw('id, full_name, birth_date, created_at, photo_path, cpf, ra, '.self::NAME_KEY.' AS name_key')
            ->whereRaw(self::NAME_KEY.' IN (SELECT '.self::NAME_KEY.' FROM students WHERE deleted_at IS NULL GROUP BY 1 HAVING count(*) > 1)')
            ->orderBy('created_at')->get();
        if ($students->isEmpty()) {
            return [];
        }

        $references = $this->references();
        $enrollments = StudentEnrollment::query()->whereIn('student_id', $students->pluck('id'))
            ->get(['id', 'student_id', 'school_id', 'school_year_id', 'status'])->groupBy('student_id');

        $groups = [];
        foreach ($students->groupBy('name_key') as $sameName) {
            $dates = $sameName->pluck('birth_date')->filter()->map(fn ($date) => (string) $date)->unique();
            // Datas diferentes são pessoas diferentes; sem data, só se houver uma única data no grupo.
            $clusters = $dates->count() <= 1 ? [$sameName] : $dates->map(fn (string $date) => $sameName->filter(fn ($s) => (string) $s->birth_date === $date))->all();
            foreach ($clusters as $cluster) {
                if ($cluster->count() < 2) {
                    continue;
                }
                $groups[] = $this->decide($cluster->values(), $references, $enrollments);
            }
        }

        return $groups;
    }

    /** @return array{name: string, keeper: string, copies: list<array{id: string, blocked: ?string}>} */
    private function decide(Collection $cluster, array $references, Collection $enrollments): array
    {
        $refs = $cluster->mapWithKeys(fn (Student $s): array => [$s->id => $this->usage($s->id, $enrollments->get($s->id, collect()), $references)]);
        $keeper = $cluster->sortByDesc(fn (Student $s): string => sprintf('%06d%d%d%d%d%s',
            array_sum($refs[$s->id]), $s->birth_date !== null, $s->photo_path !== null, $s->cpf !== null, $s->ra !== null,
            str_pad((string) (PHP_INT_MAX - $s->created_at->getTimestamp()), 20, '0', STR_PAD_LEFT),
        ))->first();
        $keeperSlots = $enrollments->get($keeper->id, collect())->map(fn ($e) => $e->school_id.'|'.$e->school_year_id)->all();

        $copies = [];
        foreach ($cluster as $student) {
            if ($student->id === $keeper->id) {
                continue;
            }
            $own = array_keys(array_filter($refs[$student->id]));
            $entries = $enrollments->get($student->id, collect());
            $blocked = match (true) {
                $own !== [] => 'tem registros próprios: '.implode(', ', $own),
                $entries->contains(fn ($e) => $e->status !== 'active') => 'tem matrícula encerrada ou transferida',
                $entries->contains(fn ($e) => ! in_array($e->school_id.'|'.$e->school_year_id, $keeperSlots, true)) => 'está matriculado em outra escola ou ano',
                default => null,
            };
            $copies[] = ['id' => $student->id, 'blocked' => $blocked];
        }

        return ['name' => $keeper->full_name, 'keeper' => $keeper->id, 'copies' => $copies];
    }

    /**
     * Tabelas que apontam para alunos e matrículas, lidas do próprio banco: uma tabela
     * nova que guarde algo do aluno já entra na conta sem mudar este código.
     *
     * @return list<array{table: string, column: string, target: string}>
     */
    private function references(): array
    {
        $rows = DB::connection('tenant')->select(<<<'SQL'
            SELECT cl.relname AS table_name, a.attname AS column_name, rf.relname AS target
            FROM pg_constraint c
            JOIN pg_class cl ON cl.oid = c.conrelid
            JOIN pg_class rf ON rf.oid = c.confrelid
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
            WHERE c.contype = 'f' AND rf.relname IN ('students', 'student_enrollments')
              AND cl.relnamespace = rf.relnamespace AND cl.relname <> 'student_enrollments'
            ORDER BY 1, 2
            SQL);

        return array_map(fn ($row): array => ['table' => $row->table_name, 'column' => $row->column_name, 'target' => $row->target], $rows);
    }

    /** @return array<string, int> registros próprios por tabela (0 ou 1) */
    private function usage(string $studentId, Collection $entries, array $references): array
    {
        $grammar = DB::connection('tenant')->getQueryGrammar();
        $usage = [];
        foreach ($references as $ref) {
            $ids = $ref['target'] === 'students' ? [$studentId] : $entries->pluck('id')->all();
            if ($ids === []) {
                continue;
            }
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $found = DB::connection('tenant')->selectOne(
                'SELECT EXISTS (SELECT 1 FROM '.$grammar->wrapTable($ref['table']).' WHERE '.$grammar->wrap($ref['column']).' IN ('.$placeholders.')) AS found',
                $ids,
            );
            $usage[$ref['table']] = ($usage[$ref['table']] ?? 0) | (int) (bool) $found->found;
        }

        return $usage;
    }
}
