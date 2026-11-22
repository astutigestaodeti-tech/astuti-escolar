<?php

namespace App\Import\Transport;

use App\Import\Students\ImportText;

/**
 * CSV da Secretaria: só lê os campos mínimos para identificar um vínculo.
 * As demais colunas são ignoradas, não armazenadas nem mostradas na prévia.
 */
class TransportCsvReader
{
    private const MAX_ROWS = 500;

    /** @return list<array{ra: ?string, name: ?string, birth_date: ?string, school: ?string, status: string, line: int}> */
    public function read(string $path): array
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new TransportCsvException('Não foi possível ler o CSV.');
        }

        try {
            $first = fgets($stream);
            if ($first === false) {
                throw new TransportCsvException('O arquivo CSV está vazio.');
            }

            $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
            rewind($stream);

            $line = 0;
            $columns = null;
            $rows = [];
            $seen = [];

            // Cada registro ocupa uma linha física. Aspas abertas não podem consumir
            // outras linhas e ocultar alunos da conferência.
            while (($rawLine = fgets($stream)) !== false) {
                $line++;
                if ($this->closedQuotes($rawLine) === false) {
                    throw new TransportCsvException('Aspas não fechadas na linha '.$line.'. Corrija o CSV antes de continuar.');
                }

                $cells = str_getcsv($rawLine, $delimiter, '"', '');
                if (count($cells) > 64) {
                    throw new TransportCsvException('A linha '.$line.' tem colunas em excesso.');
                }

                $cells = array_map(fn (?string $cell): string => $this->toUtf8((string) ($cell ?? '')), $cells);
                if ($line === 1) {
                    $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]) ?? $cells[0];
                    $columns = $this->columns($cells);

                    continue;
                }

                if (count(array_filter($cells, fn (string $cell): bool => trim($cell) !== '')) === 0) {
                    continue;
                }

                // Identificação: RA quando houver; senão, nome com data de nascimento.
                // A lista da Secretaria (SME) não traz RA e cobre várias escolas.
                $rawRa = isset($columns['ra']) ? ImportText::clean($cells[$columns['ra']] ?? null) : null;
                $ra = null;
                if ($rawRa !== null) {
                    $ra = ImportText::ra($rawRa);
                    if ($ra === null) {
                        throw new TransportCsvException('RA inválido na linha '.$line.': "'.$rawRa.'".');
                    }
                    $ra = preg_replace('/\A(\d{9,12})-?([\dX]\/\w{2})\z/', '$1-$2', strtoupper($ra)) ?? $ra;
                }

                $name = isset($columns['name']) ? ImportText::personName($cells[$columns['name']] ?? null) : null;
                $birth = isset($columns['birth']) ? $this->date($cells[$columns['birth']] ?? null) : null;
                $school = isset($columns['school']) ? ImportText::clean($cells[$columns['school']] ?? null) : null;
                if ($ra === null && $name === null) {
                    throw new TransportCsvException('A linha '.$line.' não tem RA nem nome do aluno.');
                }

                $key = $ra !== null
                    ? 'ra:'.str_replace('-', '', $ra)
                    : 'nome:'.ImportText::key($name).'|'.($birth ?? '').'|'.ImportText::key($school);
                if (isset($seen[$key])) {
                    throw new TransportCsvException(($ra !== null ? 'RA repetido' : 'Aluno repetido').' nas linhas '.$seen[$key].' e '.$line.'.');
                }
                $seen[$key] = $line;

                $status = match (ImportText::key($cells[$columns['status']] ?? null)) {
                    'sim', 's', 'ativo', 'ativa', 'atendido', 'atendida' => 'active',
                    'nao', 'n', 'sem transporte' => 'not_requested',
                    'cancelado', 'cancelada' => 'cancelled',
                    default => null,
                };
                if ($status === null) {
                    throw new TransportCsvException('Situação de transporte desconhecida na linha '.$line.'.');
                }

                $rows[] = ['ra' => $ra, 'name' => $name, 'birth_date' => $birth, 'school' => $school, 'status' => $status, 'line' => $line];
                if (count($rows) > self::MAX_ROWS) {
                    throw new TransportCsvException('O CSV excede 500 alunos. Divida a lista por escola.');
                }
            }

            if ($rows === []) {
                throw new TransportCsvException('O CSV não contém alunos para conferir.');
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private const HEADERS = [
        'ra' => ['ra', 'r a', 'ra sed', 'ra do aluno', 'registro do aluno', 'ra ficticio'],
        'status' => ['situacao', 'status', 'transporte', 'utiliza transporte', 'utiliza transporte escolar', 'situacao do transporte'],
        'name' => ['nome', 'aluno', 'nome aluno', 'nome do aluno', 'nome completo'],
        'birth' => ['dt nasc', 'data nasc', 'nascimento', 'data de nascimento', 'data nascimento', 'dt nascimento'],
        'school' => ['escola', 'unidade', 'unidade escolar', 'nome da escola'],
    ];

    private const LABELS = ['ra' => 'RA', 'status' => 'situação do transporte', 'name' => 'nome', 'birth' => 'data de nascimento', 'school' => 'escola'];

    private function columns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $value) {
            $key = ImportText::key($value);
            foreach (self::HEADERS as $field => $names) {
                if (in_array($key, $names, true)) {
                    if (isset($columns[$field])) {
                        throw new TransportCsvException('Cabeçalho ambíguo: mais de uma coluna de '.self::LABELS[$field].'.');
                    }
                    $columns[$field] = $index;
                }
            }
        }

        if (! isset($columns['status']) || (! isset($columns['ra']) && ! isset($columns['name']))) {
            $found = array_map(fn (string $field): string => self::LABELS[$field], array_keys($columns));
            throw new TransportCsvException(
                'O CSV precisa da coluna Situação e de uma forma de identificar o aluno: RA, ou Nome com Data de nascimento. '
                .($found === [] ? 'Nenhuma dessas colunas foi reconhecida no cabeçalho.' : 'Colunas reconhecidas: '.implode(', ', $found).'.')
            );
        }

        return $columns;
    }

    /** Aceita 31/12/2019, 31-12-2019 e 2019-12-31; data impossível vira null. */
    private function date(?string $value): ?string
    {
        $value = ImportText::clean($value);
        if ($value === null) {
            return null;
        }

        if (preg_match('/\A(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})\z/', $value, $m) === 1) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $m) === 1) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        // Esses cinco bytes não têm caracteres definidos em Windows-1252.
        // Recusá-los evita que um aviso do iconv vire erro HTTP 500.
        if (preg_match('/[\x81\x8D\x8F\x90\x9D]/', $value) === 1) {
            throw new TransportCsvException('O arquivo contém caracteres inválidos. Salve como UTF-8 ou Windows-1252.');
        }

        $converted = iconv('Windows-1252', 'UTF-8', $value);
        if ($converted === false) {
            throw new TransportCsvException('O arquivo contém caracteres inválidos. Salve como UTF-8 ou Windows-1252.');
        }

        return $converted;
    }

    private function closedQuotes(string $line): bool
    {
        $quoted = false;
        $length = strlen($line);
        for ($index = 0; $index < $length; $index++) {
            if ($line[$index] !== '"') {
                continue;
            }

            if ($quoted && $index + 1 < $length && $line[$index + 1] === '"') {
                $index++;

                continue;
            }

            $quoted = $quoted === false;
        }

        return $quoted === false;
    }
}
