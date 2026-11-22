<?php

namespace App\Import\Xlsx;

use DateTimeImmutable;
use XMLReader;
use ZipArchive;

/**
 * Leitor minimo de planilhas .xlsx, somente leitura e sem dependencias.
 *
 * Entrega os valores como o Excel os guarda: texto, numero, booleano ou data
 * (quando a celula numerica usa formato de data). Formulas nao sao avaliadas;
 * usa-se o ultimo valor calculado salvo no arquivo.
 */
final class XlsxReader
{
    private const MAX_ENTRY_BYTES = 20 * 1024 * 1024;

    private const MAX_ROWS = 20000;

    private const MAX_COLUMNS = 200;

    private const MAX_SHEETS = 50;

    /** Formatos internos do Excel que representam data ou hora. */
    private const BUILTIN_DATE_FORMATS = [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 45, 46, 47, 50, 51, 52, 53, 54, 55, 56, 57, 58];

    /** @return list<XlsxSheet> */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new XlsxReadException('Arquivo nao encontrado ou sem permissao de leitura.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new XlsxReadException('O arquivo nao e uma planilha .xlsx valida.');
        }

        try {
            $workbook = $this->entry($zip, 'xl/workbook.xml');
            if ($workbook === null) {
                throw new XlsxReadException('O arquivo nao e uma planilha .xlsx valida.');
            }

            $date1904 = (bool) preg_match('/<(?:\w+:)?workbookPr\b[^>]*\bdate1904="(?:1|true)"/', $workbook);
            $relations = $this->relations($this->entry($zip, 'xl/_rels/workbook.xml.rels') ?? '');
            $strings = $this->sharedStrings($this->entry($zip, 'xl/sharedStrings.xml'));
            $dateStyles = $this->dateStyles($this->entry($zip, 'xl/styles.xml'));

            $sheets = [];
            foreach ($this->sheetRefs($workbook) as [$name, $relationId]) {
                if (count($sheets) >= self::MAX_SHEETS) {
                    throw new XlsxReadException('A planilha tem abas demais.');
                }

                $target = $relations[$relationId] ?? null;
                if ($target === null) {
                    continue;
                }

                $target = ltrim($target, '/');
                $entryName = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                $xml = $this->entry($zip, $entryName);
                if ($xml === null) {
                    continue;
                }

                $sheets[] = new XlsxSheet($name, $this->rows($xml, $strings, $dateStyles, $date1904));
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    private function entry(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }

        if ($stat['size'] > self::MAX_ENTRY_BYTES) {
            throw new XlsxReadException('A planilha excede o tamanho permitido.');
        }

        $content = $zip->getFromName($name);

        return $content === false ? null : $content;
    }

    private function reader(string $xml): XMLReader
    {
        $reader = new XMLReader;
        if ($reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT) === false) {
            throw new XlsxReadException('Conteudo interno da planilha invalido.');
        }

        return $reader;
    }

    /** @return array<string, string> */
    private function relations(string $xml): array
    {
        $relations = [];
        if ($xml === '') {
            return $relations;
        }

        $reader = $this->reader($xml);
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                $id = $reader->getAttribute('Id');
                $target = $reader->getAttribute('Target');
                if ($id !== null && $target !== null) {
                    $relations[$id] = $target;
                }
            }
        }

        return $relations;
    }

    /** @return list<array{string, string}> */
    private function sheetRefs(string $xml): array
    {
        $refs = [];
        $reader = $this->reader($xml);
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $name = $reader->getAttribute('name');
                $id = $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                if ($name !== null && $id !== null) {
                    $refs[] = [$name, $id];
                }
            }
        }

        return $refs;
    }

    /** @return list<string> */
    private function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }

        $strings = [];
        $reader = $this->reader($xml);
        $current = null;
        $inPhonetic = false;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'si') {
                    $current = '';
                } elseif ($reader->localName === 'rPh') {
                    $inPhonetic = true;
                } elseif ($reader->localName === 't' && $current !== null && ! $inPhonetic) {
                    $current .= $reader->readString();
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                if ($reader->localName === 'si' && $current !== null) {
                    $strings[] = $current;
                    $current = null;
                } elseif ($reader->localName === 'rPh') {
                    $inPhonetic = false;
                }
            }
        }

        return $strings;
    }

    /** @return array<int, true> indices de estilo (cellXfs) que exibem data */
    private function dateStyles(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }

        $customFormats = [];
        $styles = [];
        $reader = $this->reader($xml);
        $inCellXfs = false;
        $index = 0;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'numFmt') {
                    $id = (int) $reader->getAttribute('numFmtId');
                    $customFormats[$id] = (string) $reader->getAttribute('formatCode');
                } elseif ($reader->localName === 'cellXfs') {
                    $inCellXfs = true;
                } elseif ($reader->localName === 'xf' && $inCellXfs) {
                    $formatId = (int) $reader->getAttribute('numFmtId');
                    $isDate = in_array($formatId, self::BUILTIN_DATE_FORMATS, true)
                        || (isset($customFormats[$formatId]) && $this->isDateFormat($customFormats[$formatId]));
                    if ($isDate) {
                        $styles[$index] = true;
                    }
                    $index++;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'cellXfs') {
                $inCellXfs = false;
            }
        }

        return $styles;
    }

    private function isDateFormat(string $format): bool
    {
        // Remove textos literais, cores/condicoes e escapes antes de procurar d, m, y.
        $clean = preg_replace(['/"[^"]*"/', '/\[[^\]]*\]/', '/\\\\./'], '', $format) ?? '';

        return (bool) preg_match('/[dmy]/i', $clean) && ! str_contains($clean, '0');
    }

    /**
     * @param  list<string>  $strings
     * @param  array<int, true>  $dateStyles
     * @return array<int, array<int, string|int|float|bool|DateTimeImmutable>>
     */
    private function rows(string $xml, array $strings, array $dateStyles, bool $date1904): array
    {
        $rows = [];
        $reader = $this->reader($xml);
        $rowNumber = 0;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->localName === 'row') {
                $declared = $reader->getAttribute('r');
                $rowNumber = $declared !== null ? (int) $declared : $rowNumber + 1;
                if ($rowNumber > self::MAX_ROWS) {
                    throw new XlsxReadException('A planilha tem linhas demais.');
                }

                continue;
            }

            if ($reader->localName !== 'c') {
                continue;
            }

            $reference = (string) $reader->getAttribute('r');
            $type = $reader->getAttribute('t') ?? 'n';
            $style = $reader->getAttribute('s');
            $column = $this->columnIndex($reference);
            if ($column === null) {
                continue;
            }

            if ($column >= self::MAX_COLUMNS) {
                throw new XlsxReadException('A planilha tem colunas demais.');
            }

            [$raw, $inline] = $this->cellContent($reader);
            $value = $this->value($type, $raw, $inline, $strings, $style !== null && isset($dateStyles[(int) $style]), $date1904);
            if ($value === null) {
                continue;
            }

            $rowFromReference = $this->rowIndex($reference) ?? $rowNumber;
            $rows[$rowFromReference][$column] = $value;
        }

        ksort($rows);
        foreach ($rows as &$cells) {
            ksort($cells);
        }

        return $rows;
    }

    /** @return array{?string, ?string} valor <v> e texto <is><t> */
    private function cellContent(XMLReader $reader): array
    {
        if ($reader->isEmptyElement) {
            return [null, null];
        }

        $raw = null;
        $inline = null;
        $depth = $reader->depth;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT) {
                if ($reader->localName === 'v') {
                    $raw = $reader->readString();
                } elseif ($reader->localName === 't') {
                    $inline = ($inline ?? '').$reader->readString();
                }
            }
        }

        return [$raw, $inline];
    }

    /** @param list<string> $strings */
    private function value(string $type, ?string $raw, ?string $inline, array $strings, bool $isDate, bool $date1904): string|int|float|bool|DateTimeImmutable|null
    {
        return match ($type) {
            's' => $raw !== null && isset($strings[(int) $raw]) ? $strings[(int) $raw] : null,
            'inlineStr' => $inline,
            'str' => $raw,
            'b' => $raw === null ? null : $raw === '1',
            'e' => null,
            default => $this->number($raw, $isDate, $date1904),
        };
    }

    private function number(?string $raw, bool $isDate, bool $date1904): int|float|DateTimeImmutable|null
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $number = $raw + 0;
        if ($isDate) {
            return ExcelDate::fromSerial((float) $number, $date1904);
        }

        return $number;
    }

    private function columnIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]{1,3})\d+$/', $reference, $match)) {
            return null;
        }

        $index = 0;
        foreach (str_split($match[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function rowIndex(string $reference): ?int
    {
        return preg_match('/(\d+)$/', $reference, $match) ? (int) $match[1] : null;
    }
}
