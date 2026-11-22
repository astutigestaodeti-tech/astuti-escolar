<?php

namespace App\Import\Students;

use App\Import\Xlsx\XlsxReader;
use App\Import\Xlsx\XlsxSheet;

/**
 * Le a planilha de alunos de UMA escola e monta a previa da importacao.
 *
 * Somente leitura: nao grava nada. Cada aluno sai com a origem (aba e linha)
 * e com o que precisa de conferencia humana ou ficou pendente.
 */
final class StudentSpreadsheetPreview
{
    /** Cabecalhos conhecidos, ja na forma de ImportText::key(). */
    private const HEADERS = [
        'name' => ['nome da crianca', 'nome do aluno', 'nome do a aluno', 'nome da aluna', 'aluno', 'aluno a', 'aluna', 'nome', 'crianca', 'estudante', 'nome completo'],
        'birth' => ['d n', 'dn', 'data de nascimento', 'nascimento', 'nasc', 'nasc idade', 'data nasc', 'dt nasc', 'data nascimento'],
        'ra' => ['ra', 'r a', 'registro do aluno', 'ra sed'],
        'class' => ['serie turma', 'turma', 'classe', 'ano turma'],
        'grade' => ['ano', 'serie', 'ano serie', 'ano escolar'],
        'guardian' => ['mae', 'nome da mae', 'responsavel', 'pai ou mae', 'responsavel legal', 'nome do responsavel'],
        'phone' => ['telefone', 'fone', 'tel', 'celular', 'contato', 'telefone do responsavel'],
        'cpf' => ['cpf resp', 'cpf do responsavel', 'cpf responsavel', 'cpf'],
        'address' => ['endereco', 'endereco completo', 'logradouro'],
        'number' => ['numero', 'numero da residencia', 'numero da casa'],
        'residence' => ['bairro', 'bairro da residencia', 'bairro de residencia'],
        'postal_code' => ['cep', 'codigo postal'],
        'residence_zone' => ['zona de residencia', 'zona residencial', 'zona'],
        'transport' => ['transp', 'transporte', 'onibus', 'transporte escolar', 'usa transporte'],
        'notes' => ['obs', 'observacao', 'observacoes'],
        // Dados do Censo Escolar (exportações da SED e listas completas).
        'social_name' => ['nome social', 'nome social do aluno'],
        'sex' => ['sexo', 'sexo do aluno'],
        'race_color' => ['cor raca', 'raca cor', 'cor ou raca', 'cor da pele', 'raca', 'cor'],
        'nationality' => ['nacionalidade'],
        'birthplace' => ['naturalidade', 'municipio de nascimento', 'cidade de nascimento', 'local de nascimento', 'cidade natal'],
        'birthplace_state' => ['uf de nascimento', 'uf nascimento', 'uf natal', 'estado de nascimento'],
        'student_cpf' => ['cpf do aluno', 'cpf aluno', 'cpf da crianca', 'cpf do estudante', 'cpf do a aluno', 'cpf ficticio'],
    ];

    /** Rótulos das pendências do Censo, na linguagem da secretaria. */
    private const CENSUS_LABELS = [
        'sex' => 'Sexo', 'race_color' => 'Cor/raça', 'nationality' => 'Nacionalidade',
    ];

    private const HEADER_SEARCH_ROWS = 20;

    /** Abaixo desta confianca de leitura (OCR), a linha exige conferencia. */
    public const OCR_CONFIDENCE_MIN = 85.0;

    /** Campo correspondente a um titulo de coluna, ou null se desconhecido. */
    public static function fieldForHeader(mixed $value): ?string
    {
        $key = ImportText::key(ImportText::clean($value));
        foreach (self::HEADERS as $field => $synonyms) {
            if (in_array($key, $synonyms, true)) {
                return $field;
            }
        }

        return null;
    }

    public function __construct(private readonly XlsxReader $reader) {}

    public function fromFile(string $path, ImportTarget $target): ImportPreview
    {
        return $this->fromSheets($this->reader->read($path), $target);
    }

    /** @param list<XlsxSheet> $sheets */
    public function fromSheets(array $sheets, ImportTarget $target): ImportPreview
    {
        $preview = new ImportPreview;

        foreach ($sheets as $sheet) {
            [$headerRow, $columns] = $this->header($sheet);
            if ($headerRow === null) {
                $preview->ignore('Aba '.$sheet->name, 'Nao encontrei o cabecalho com o nome do aluno; a aba foi ignorada.');

                continue;
            }

            foreach ($sheet->rows as $rowNumber => $cells) {
                if ($rowNumber <= $headerRow) {
                    continue;
                }

                // PDF e foto: "Página 2, linha 5"; planilha: "Aba Alunos, linha 5".
                $place = ($sheet->name === 'Documento' || str_starts_with($sheet->name, 'Página ')) ? $sheet->name : 'Aba '.$sheet->name;
                $source = $place.', linha '.$rowNumber;
                $student = $this->student($sheet, $cells, $columns, $target, $source, $preview);
                if ($student !== null) {
                    $reading = $sheet->confidence[$rowNumber] ?? null;
                    if ($reading !== null && $reading < self::OCR_CONFIDENCE_MIN) {
                        $student->addIssue(ImportIssue::CONFIRM, 'leitura', 'Leitura automática com baixa confiança ('.number_format($reading, 0).'%). Confira nome, data e RA com o documento.');
                    }
                    $preview->students[] = $student;
                }
            }
        }

        $preview->students = $this->mergeDuplicates($preview->students);
        $this->flagSharedRa($preview->students);

        return $preview;
    }

    /** @return array{?int, array<string, int>} */
    private function header(XlsxSheet $sheet): array
    {
        foreach ($sheet->rows as $rowNumber => $cells) {
            if ($rowNumber > self::HEADER_SEARCH_ROWS) {
                break;
            }

            $columns = [];
            foreach ($cells as $column => $value) {
                $key = ImportText::key(ImportText::clean($value));
                foreach (self::HEADERS as $field => $synonyms) {
                    if (! isset($columns[$field]) && in_array($key, $synonyms, true)) {
                        $columns[$field] = $column;
                        break;
                    }
                }
            }

            if (isset($columns['name']) && count($columns) >= 2) {
                return [$rowNumber, $columns];
            }
        }

        return [null, []];
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $columns
     */
    private function student(XlsxSheet $sheet, array $cells, array $columns, ImportTarget $target, string $source, ImportPreview $preview): ?ImportedStudent
    {
        $cell = fn (string $field): mixed => isset($columns[$field]) ? ($cells[$columns[$field]] ?? null) : null;

        $filled = array_filter($cells, fn (mixed $value): bool => ImportText::clean($value) !== null);
        if ($filled === []) {
            return null;
        }

        $name = ImportText::personName($cell('name'));
        if ($name === null) {
            $preview->ignore($source, 'Linha sem nome de aluno; nada foi aproveitado.');

            return null;
        }

        if (preg_match('/^total\b/', ImportText::key($name)) === 1) {
            $preview->ignore($source, 'Linha de total ("'.$name.'"), nao e aluno.');

            return null;
        }

        $birth = ImportText::birthDate($cell('birth'), $target->referenceDate);
        $ra = ImportText::ra($cell('ra'));
        [$classId, $classIssue] = $this->classFor($sheet, ImportText::clean($cell('class')), $target);

        $grade = null;
        $gradeText = ImportText::clean($cell('grade'));
        if ($gradeText !== null) {
            $grade = preg_match('/^(\d{1,2})\s*(?:º|°|o)?\s*(?:ano)?$/iu', $gradeText, $m) ? $m[1].'º ano' : $gradeText;
        }

        $address = ['street' => null, 'number' => null, 'district' => null];
        if (isset($columns['address'])) {
            $address = ImportText::address($cell('address'));
        }
        if (isset($columns['number'])) {
            $address['number'] = ImportText::clean($cell('number')) ?? $address['number'];
        }
        $districtConflict = false;
        if (isset($columns['residence'])) {
            $separateDistrict = ImportText::clean($cell('residence'));
            if ($separateDistrict !== null && $address['district'] === null) {
                $address['district'] = $separateDistrict;
            } elseif ($separateDistrict !== null && ImportText::key($separateDistrict) !== ImportText::key($address['district'])) {
                // Duas fontes divergentes não autorizam escolher um bairro arbitrariamente.
                $address['district'] = null;
                $districtConflict = true;
            }
        }
        $postalCode = isset($columns['postal_code']) ? ImportText::postalCode($cell('postal_code')) : null;
        $residenceZone = isset($columns['residence_zone']) ? ImportText::residenceZone($cell('residence_zone')) : null;

        $student = new ImportedStudent(
            schoolId: $target->schoolId,
            name: $name,
            birthDate: $birth['date'],
            ra: $ra,
            classId: $classId,
            grade: $grade,
            usesTransport: isset($columns['transport']) ? ImportText::yesNo($cell('transport')) : null,
            street: $address['street'],
            number: $address['number'],
            district: $address['district'],
            sources: [$source],
            hints: array_filter([
                'responsavel' => ImportText::personName($cell('guardian')),
                'telefone' => ImportText::clean($cell('phone')),
                'cpf_responsavel' => ImportText::clean($cell('cpf')),
                'observacao' => ImportText::clean($cell('notes')),
                'idade_informada' => $birth['age'] !== null ? $birth['age'].' anos' : null,
            ], fn (?string $value): bool => $value !== null),
            postalCode: $postalCode,
            residenceZone: $residenceZone,
        );

        if ($classIssue !== null) {
            $student->addIssue(ImportIssue::CONFIRM, 'turma', $classIssue);
        }
        if ($birth['issue'] !== null) {
            $student->addIssue(ImportIssue::PENDING, 'data_nascimento', $birth['issue']);
        }
        if ($ra === null) {
            $raw = ImportText::clean($cell('ra'));
            $student->addIssue(ImportIssue::PENDING, 'ra', $raw === null ? 'RA não informado.' : 'RA em formato não reconhecido: "'.$raw.'".');
        }
        if ($student->usesTransport === null) {
            $student->addIssue(ImportIssue::PENDING, 'transporte', 'A planilha não informa o uso de transporte escolar.');
        }
        if ($districtConflict) {
            $student->districtConflict = true;
            $student->addIssue(ImportIssue::PENDING, 'endereco', 'Bairros divergentes nas colunas de endereço. O bairro não foi importado; confira na ficha.');
        }
        if (! $districtConflict && ($student->street === null || $student->number === null || $student->district === null)) {
            $student->addIssue(ImportIssue::PENDING, 'endereco', 'Endereço incompleto na planilha.');
        }
        if ($postalCode === null) {
            $rawCep = ImportText::clean($cell('postal_code'));
            $student->addIssue(ImportIssue::PENDING, 'cep', $rawCep === null ? 'CEP não informado.' : 'CEP inválido na planilha; informe no cadastro após a importação.');
        }
        if (isset($columns['residence_zone']) && $residenceZone === null && ImportText::clean($cell('residence_zone')) !== null) {
            $student->addIssue(ImportIssue::PENDING, 'zona_residencia', 'Zona de residência inválida na planilha; escolha Urbana ou Rural no cadastro.');
        }
        $this->readCensus($student, $cell, $columns);
        if (isset($student->hints['observacao'])) {
            $student->addIssue(ImportIssue::PENDING, 'observacao', 'Observação da escola para a etapa de inclusão: "'.$student->hints['observacao'].'".');
        }

        return $student;
    }

    /**
     * Lê as colunas do Censo que existirem. Valor reconhecido vai para o
     * cadastro; valor estranho vira pendência com o que estava escrito, para
     * a secretaria completar na ficha. Coluna ausente não gera pendência.
     *
     * @param  array<string, int>  $columns
     */
    private function readCensus(ImportedStudent $student, \Closure $cell, array $columns): void
    {
        $census = [];

        if (isset($columns['social_name'])) {
            $social = ImportText::personName($cell('social_name'));
            if ($social !== null && mb_strlen($social) >= 3 && mb_strlen($social) <= 180
                && preg_match('/\A\p{L}[\p{L}\p{M}\' .-]*\z/u', $social) === 1
                && ImportText::key($social) !== ImportText::key($student->name)) {
                $census['social_name'] = $social;
            } elseif ($social !== null && ImportText::key($social) !== ImportText::key($student->name)) {
                $student->addIssue(ImportIssue::PENDING, 'censo', 'Nome social com caracteres não aceitos ("'.$social.'"); informe na ficha.');
            }
        }

        foreach (['sex' => 'sex', 'race_color' => 'raceColor', 'nationality' => 'nationality'] as $field => $parser) {
            if (! isset($columns[$field])) {
                continue;
            }
            $raw = ImportText::clean($cell($field));
            $value = ImportText::{$parser}($raw);
            if ($value !== null) {
                $census[$field] = $value;
            } elseif ($raw !== null) {
                $student->addIssue(ImportIssue::PENDING, 'censo', self::CENSUS_LABELS[$field].' "'.$raw.'" não corresponde às opções do Censo; escolha na ficha.');
            }
        }

        if (isset($columns['birthplace'])) {
            $raw = ImportText::clean($cell('birthplace'));
            $place = ImportText::birthplace($raw);
            if (isset($columns['birthplace_state']) && $place['state'] === null) {
                $uf = strtoupper((string) ImportText::clean($cell('birthplace_state')));
                $place['state'] = isset(\App\Address\BrazilianStates::NAMES[$uf]) ? $uf : null;
            }
            if ($place['city'] !== null) {
                $census['birthplace_city'] = $place['city'];
            }
            if ($place['state'] !== null) {
                $census['birthplace_state'] = $place['state'];
            }
            if ($raw !== null && ($place['city'] === null || $place['state'] === null)) {
                $student->addIssue(ImportIssue::PENDING, 'censo', 'Naturalidade "'.$raw.'" sem cidade e UF reconhecidas (exemplo: Recife/PE); complete na ficha.');
            }
        }

        if (isset($columns['student_cpf'])) {
            $raw = ImportText::clean($cell('student_cpf'));
            $cpf = $raw === null ? null : \App\Validation\Cpf::normalize($raw);
            if ($cpf !== null && \App\Validation\Cpf::isValid($cpf)) {
                $census['cpf'] = $cpf;
            } elseif ($raw !== null) {
                $student->addIssue(ImportIssue::PENDING, 'cpf', $cpf === null
                    ? 'CPF do aluno com formato não reconhecido; não foi importado. Informe na ficha conferindo o documento.'
                    : 'CPF do aluno inválido (os dígitos verificadores não conferem); não foi importado. Confira o documento e informe na ficha.');
            }
        }

        $student->census = $census;
    }

    /** @return array{?string, ?string} id da turma e, se nao resolvida, o motivo */
    private function classFor(XlsxSheet $sheet, ?string $label, ImportTarget $target): array
    {
        $byCode = [];
        foreach ($target->classes as $class) {
            $byCode[ImportText::classKey($class->code)] = $class;
        }

        if ($label !== null) {
            $match = $byCode[ImportText::classKey($label)] ?? null;

            return $match !== null
                ? [$match->id, null]
                : [null, 'Turma "'.$label.'" não corresponde a nenhuma turma da escola.'];
        }

        $sheetMatch = $byCode[ImportText::classKey($sheet->name)] ?? null;
        if ($sheetMatch !== null) {
            return [$sheetMatch->id, null];
        }

        $byShift = array_values(array_filter(
            $target->classes,
            fn (ClassOption $class): bool => ImportText::key($class->shift) === ImportText::key($sheet->name),
        ));
        if (count($byShift) === 1) {
            return [$byShift[0]->id, null];
        }

        if (count($target->classes) === 1) {
            return [$target->classes[0]->id, null];
        }

        return [null, 'A linha não informa a turma e a aba "'.$sheet->name.'" não identifica uma turma única.'];
    }

    /**
     * Junta linhas do mesmo aluno (mesmo nome sem acento e mesma data). A juncao
     * e so uma proposta: o aluno fica em "conferir" ate alguem confirmar.
     *
     * @param  list<ImportedStudent>  $students
     * @return list<ImportedStudent>
     */
    private function mergeDuplicates(array $students): array
    {
        $groups = [];
        foreach ($students as $student) {
            $key = ImportText::key($student->name).'|'.($student->birthDate?->format('Y-m-d') ?? '?');
            $groups[$key][] = $student;
        }

        $result = [];
        foreach ($groups as $group) {
            $first = array_shift($group);
            $censusConflicts = [];
            foreach ($group as $other) {
                foreach (['ra', 'classId', 'grade', 'usesTransport', 'street', 'number', 'district', 'postalCode', 'residenceZone'] as $field) {
                    if ($field === 'district' && ($first->districtConflict || $other->districtConflict)) {
                        // Divergência em qualquer origem impede usar o bairro de outra linha.
                        $first->districtConflict = true;
                        $first->district = null;

                        continue;
                    }

                    if ($first->{$field} === null) {
                        $first->{$field} = $other->{$field};
                    } elseif ($other->{$field} !== null && $other->{$field} !== $first->{$field}) {
                        if ($field === 'district') {
                            if (ImportText::key($first->district) === ImportText::key($other->district)) {
                                continue;
                            }

                            $first->districtConflict = true;
                            $first->district = null;
                            $first->addIssue(ImportIssue::PENDING, 'endereco', 'Bairros divergentes entre linhas repetidas. O bairro não foi importado; confira na ficha.');
                        } else {
                            $first->addIssue(ImportIssue::CONFIRM, $field, 'Valores diferentes entre as linhas repetidas: "'.$this->text($first->{$field}).'" e "'.$this->text($other->{$field}).'".');
                        }
                    }
                }
                foreach ($other->census as $field => $value) {
                    if (isset($censusConflicts[$field])) {
                        continue;
                    }
                    if (! isset($first->census[$field])) {
                        $first->census[$field] = $value;
                    } elseif ($first->census[$field] !== $value) {
                        // Duas linhas discordam: nada do Censo é escolhido no escuro.
                        unset($first->census[$field]);
                        $censusConflicts[$field] = true;
                        $first->addIssue(ImportIssue::PENDING, 'censo', 'Dados do Censo diferentes entre as linhas repetidas; o campo não foi importado. Confira na ficha.');
                    }
                }
                // Pendências de outras ocorrências não podem sumir na consolidação.
                $first->issues = [...$first->issues, ...$other->issues];
                $first->sources = [...$first->sources, ...$other->sources];
                $first->hints += $other->hints;
            }

            if ($group !== []) {
                // Pendencias resolvidas pela outra linha deixam de valer.
                $first->issues = array_values(array_filter($first->issues, fn (ImportIssue $issue): bool => ! (
                    ($issue->field === 'ra' && $first->ra !== null)
                    || ($issue->field === 'transporte' && $first->usesTransport !== null)
                    || ($issue->field === 'turma' && $first->classId !== null)
                )));
                $first->addIssue(ImportIssue::CONFIRM, 'duplicidade', 'O mesmo aluno aparece '.count($first->sources).' vezes ('.implode('; ', $first->sources).'). Confirme que é a mesma pessoa.');
            }

            $result[] = $first;
        }

        return $result;
    }

    /** @param list<ImportedStudent> $students */
    private function flagSharedRa(array $students): void
    {
        $byRa = [];
        foreach ($students as $student) {
            if ($student->ra !== null) {
                $byRa[$student->ra][] = $student;
            }
        }

        foreach ($byRa as $ra => $owners) {
            if (count($owners) > 1) {
                foreach ($owners as $owner) {
                    $owner->addIssue(ImportIssue::CONFIRM, 'ra', 'O RA '.$ra.' aparece em alunos diferentes.');
                }
            }
        }
    }

    private function text(mixed $value): string
    {
        return is_bool($value) ? ($value ? 'Sim' : 'Nao') : (string) $value;
    }
}
