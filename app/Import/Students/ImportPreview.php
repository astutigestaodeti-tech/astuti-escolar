<?php

namespace App\Import\Students;

final class ImportPreview
{
    /** @var list<ImportedStudent> */
    public array $students = [];

    /** @var list<array{source: string, reason: string}> */
    public array $ignored = [];

    public function ignore(string $source, string $reason): void
    {
        $this->ignored[] = ['source' => $source, 'reason' => $reason];
    }

    /** @return array{pronto: int, conferir: int, pendente: int} */
    public function summary(): array
    {
        $summary = ['pronto' => 0, 'conferir' => 0, 'pendente' => 0];
        foreach ($this->students as $student) {
            $summary[$student->status()]++;
        }

        return $summary;
    }
}
