<?php

namespace App\Import\Students;

use DateTimeImmutable;

/**
 * Um aluno como a importacao o entendeu, com a origem de cada informacao e
 * tudo o que ainda depende de conferencia. Nada aqui foi gravado no banco.
 */
final class ImportedStudent
{
    /** @var list<ImportIssue> */
    public array $issues = [];

    /** Indica que o bairro foi invalidado por fontes divergentes na planilha. */
    public bool $districtConflict = false;

    /**
     * Dados do Censo Escolar já no formato do cadastro (sexo, cor/raça,
     * nacionalidade, naturalidade, CPF e nome social). Só entram valores
     * reconhecidos; o resto vira pendência para a ficha.
     *
     * @var array<string, string>
     */
    public array $census = [];

    /**
     * Responsável lido da planilha (nome, parentesco pelo título da coluna,
     * telefone só com DDD e CPF só se válido). Null quando não há nome.
     *
     * @var array{name: string, relationship: string, phone: ?string, cpf: ?string}|null
     */
    public ?array $guardian = null;

    /**
     * @param  list<string>  $sources  "Aba X, linha N"
     * @param  array<string, string>  $hints  pistas que nao viram cadastro sozinhas (mae, telefone, CPF, observacao)
     */
    public function __construct(
        public string $schoolId,
        public string $name,
        public ?DateTimeImmutable $birthDate,
        public ?string $ra,
        public ?string $classId,
        public ?string $grade,
        public ?bool $usesTransport,
        public ?string $street,
        public ?string $number,
        public ?string $district,
        public array $sources,
        public array $hints = [],
        public ?string $postalCode = null,
        public ?string $residenceZone = null,
    ) {}

    public function addIssue(string $kind, string $field, string $message): void
    {
        $this->issues[] = new ImportIssue($kind, $field, $message);
    }

    /** "pronto", "conferir" ou "pendente" (conferir tem prioridade). */
    public function status(): string
    {
        $kinds = array_map(fn (ImportIssue $issue): string => $issue->kind, $this->issues);
        if (in_array(ImportIssue::CONFIRM, $kinds, true)) {
            return ImportIssue::CONFIRM;
        }

        return $kinds === [] ? 'pronto' : ImportIssue::PENDING;
    }

    /** @return list<ImportIssue> */
    public function issuesOf(string $kind): array
    {
        return array_values(array_filter($this->issues, fn (ImportIssue $issue): bool => $issue->kind === $kind));
    }
}
