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

    /** @return list<array{ra: string, name: ?string, status: string, line: int}> */
    public function read(string $path): array
    {
        $stream = @fopen($path, 'rb');
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

            while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $line++;
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

                $ra = ImportText::ra($cells[$columns['ra']] ?? null);
                if ($ra === null) {
                    throw new TransportCsvException('RA ausente ou inválido na linha '.$line.'.');
                }

                $ra = preg_replace('/\A(\d{9,12})-?([\dX]\/\w{2})\z/', '$1-$2', strtoupper($ra)) ?? $ra;
                $key = str_replace('-', '', $ra);
                if (isset($seen[$key])) {
                    throw new TransportCsvException('RA repetido nas linhas '.$seen[$key].' e '.$line.'.');
                }
                $seen[$key] = $line;

                $status = match (ImportText::key($cells[$columns['status']] ?? null)) {
                    'sim', 'ativo', 'ativa', 'atendido', 'atendida' => 'active',
                    'nao', 'sem transporte' => 'not_requested',
                    'cancelado', 'cancelada' => 'cancelled',
                    default => null,
                };
                if ($status === null) {
                    throw new TransportCsvException('Situação de transporte desconhecida na linha '.$line.'.');
                }

                $name = isset($columns['name']) ? ImportText::personName($cells[$columns['name']] ?? null) : null;
                $rows[] = ['ra' => $ra, 'name' => $name, 'status' => $status, 'line' => $line];
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

    /** @param list<string> $header
     *  @return array{ra: int, status: int, name?: int}
     */
    private function columns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $value) {
            $key = ImportText::key($value);
            if (in_array($key, ['ra', 'r a', 'ra sed', 'registro do aluno'], true)) {
                $columns['ra'] ??= $index;
            } elseif (in_array($key, ['situacao', 'status', 'transporte', 'utiliza transporte escolar', 'situacao do transporte'], true)) {
                $columns['status'] ??= $index;
            } elseif (in_array($key, ['nome', 'aluno', 'nome do aluno', 'nome completo'], true)) {
                $columns['name'] ??= $index;
            }
        }

        if (! isset($columns['ra'], $columns['status'])) {
            throw new TransportCsvException('O CSV precisa das colunas RA e situação do transporte.');
        }

        return $columns;
    }

    private function toUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = iconv('Windows-1252', 'UTF-8', $value);
        if ($converted === false) {
            throw new TransportCsvException('O arquivo contém caracteres inválidos. Salve como UTF-8 ou Windows-1252.');
        }

        return $converted;
    }
}
