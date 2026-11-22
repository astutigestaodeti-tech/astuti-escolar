<?php

namespace App\Import\Ocr;

use App\Import\Students\StudentSpreadsheetPreview;
use App\Import\Xlsx\XlsxSheet;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Le listas de alunos em PDF (digital ou escaneado) e em foto (JPG/PNG) e
 * devolve tabelas no mesmo formato das planilhas, para a mesma conferencia.
 *
 * Tudo roda no proprio servidor (Poppler e Tesseract): o documento nunca vai
 * para servico externo. Cada linha lida por OCR leva a menor confianca das
 * suas palavras; abaixo do limite, a conferencia humana e obrigatoria.
 */
final class DocumentTableReader
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_PAGES = 10;

    private const MAX_PIXELS = 60_000_000;

    private const TIMEOUT_SECONDS = 90;

    private const DPI = 300;

    /** Linhas acima do cabecalho que podem ser so titulo. */
    private const HEADER_SEARCH_LINES = 12;

    /** @return list<XlsxSheet> */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new OcrReadException('Arquivo nao encontrado ou sem permissao de leitura.');
        }
        if (filesize($path) > self::MAX_BYTES) {
            throw new OcrReadException('O documento pode ter no maximo 10 MB.');
        }

        $kind = $this->kind($path);
        $work = storage_path('app/private/ocr/'.Str::uuid());
        File::ensureDirectoryExists($work, 0700);

        try {
            return match ($kind) {
                'pdf' => $this->readPdf($path, $work),
                default => [$this->sheet('Foto', $this->ocrImage($this->checkedImage($path)))],
            };
        } finally {
            // Copias temporarias de documentos de alunos nao ficam no servidor.
            File::deleteDirectory($work);
        }
    }

    /** Tipo pelo conteudo do arquivo, nunca pelo nome. */
    public function kind(string $path): string
    {
        $head = (string) file_get_contents($path, false, null, 0, 8);

        return match (true) {
            str_starts_with($head, '%PDF-') => 'pdf',
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            default => throw new OcrReadException('Envie um PDF ou uma foto em JPG ou PNG.'),
        };
    }

    /** @return list<XlsxSheet> */
    private function readPdf(string $path, string $work): array
    {
        $pages = $this->pageCount($path);
        if ($pages < 1) {
            throw new OcrReadException('O PDF nao tem paginas legiveis.');
        }
        if ($pages > self::MAX_PAGES) {
            throw new OcrReadException('O PDF pode ter no maximo '.self::MAX_PAGES.' paginas por envio.');
        }

        // PDF digital: o texto ja esta no arquivo, sem OCR e sem duvida de leitura.
        $digital = $this->pdfWords($path, $work, $pages);
        if ($digital !== null) {
            $sheets = [];
            foreach ($digital as $page => $words) {
                $sheets[] = $this->sheet($pages > 1 ? 'Pagina '.$page : 'Documento', $words);
            }

            return $sheets;
        }

        // PDF escaneado: cada pagina vira imagem e passa pelo OCR.
        $this->run(['pdftoppm', '-r', (string) self::DPI, '-gray', '-png', '-f', '1', '-l', (string) $pages, $path, $work.'/pagina']);
        $images = glob($work.'/pagina-*.png') ?: [];
        natsort($images);

        $sheets = [];
        foreach (array_values($images) as $index => $image) {
            $sheets[] = $this->sheet($pages > 1 ? 'Pagina '.($index + 1) : 'Documento', $this->ocrImage($image));
        }

        return $sheets;
    }

    private function pageCount(string $path): int
    {
        $info = $this->run(['pdfinfo', $path]);

        return preg_match('/^Pages:\s+(\d+)/m', $info, $m) ? (int) $m[1] : 0;
    }

    /**
     * Palavras do PDF com posicao. Null quando o PDF nao tem texto suficiente
     * (documento escaneado).
     *
     * @return array<int, list<array{text: string, x: float, y: float, w: float, h: float, conf: float, line: string}>>|null
     */
    private function pdfWords(string $path, string $work, int $pages): ?array
    {
        $html = $work.'/texto.html';
        $this->run(['pdftotext', '-bbox-layout', '-f', '1', '-l', (string) $pages, $path, $html]);

        $reader = new \XMLReader;
        if (! is_file($html) || $reader->open($html, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING) === false) {
            return null;
        }

        $result = [];
        $page = 0;
        $line = 0;
        $total = 0;
        while (@$reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->localName === 'page') {
                $page++;
            } elseif ($reader->localName === 'line') {
                $line++;
            } elseif ($reader->localName === 'word') {
                $x = (float) $reader->getAttribute('xMin');
                $y = (float) $reader->getAttribute('yMin');
                $w = (float) $reader->getAttribute('xMax') - $x;
                $h = (float) $reader->getAttribute('yMax') - $y;
                $text = trim($reader->readString());
                if ($text !== '') {
                    $result[$page][] = ['text' => $text, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'conf' => 100.0, 'line' => $page.'-'.$line];
                    $total++;
                }
            }
        }
        $reader->close();

        if ($total < 10) {
            return null;
        }

        // O pdftotext separa colunas em "linhas" diferentes; a linha da tabela
        // e definida pela altura na pagina.
        foreach ($result as $number => $pageWords) {
            usort($pageWords, fn (array $a, array $b): int => $a['y'] <=> $b['y'] ?: $a['x'] <=> $b['x']);
            $lineY = null;
            $lineIndex = 0;
            foreach ($pageWords as &$word) {
                if ($lineY === null || abs($word['y'] - $lineY) > $word['h'] * 0.6) {
                    $lineIndex++;
                    $lineY = $word['y'];
                }
                $word['line'] = $number.'-'.$lineIndex;
            }
            unset($word);
            $result[$number] = $pageWords;
        }

        return $result;
    }

    private function checkedImage(string $path): string
    {
        $size = @getimagesize($path);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new OcrReadException('Nao foi possivel abrir a imagem.');
        }
        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw new OcrReadException('A imagem e grande demais. Envie uma foto com ate 60 megapixels.');
        }

        return $path;
    }

    /** @return list<array{text: string, x: float, y: float, w: float, h: float, conf: float, line: string}> */
    private function ocrImage(string $image): array
    {
        // psm 4: uma coluna de linhas de tamanho variavel, o que mantem cada
        // linha da tabela inteira (nome, RA, data e turma juntos).
        $tsv = $this->run(['tesseract', $image, 'stdout', '-l', 'por', '--psm', '4', 'tsv']);

        $words = [];
        foreach (preg_split('/\R/', $tsv) ?: [] as $index => $row) {
            $cols = explode("\t", $row);
            if ($index === 0 || count($cols) < 12 || $cols[0] !== '5') {
                continue;
            }
            $text = trim($cols[11]);
            if ($text === '') {
                continue;
            }
            $words[] = [
                'text' => $text,
                'x' => (float) $cols[6], 'y' => (float) $cols[7], 'w' => (float) $cols[8], 'h' => (float) $cols[9],
                'conf' => max(0.0, (float) $cols[10]),
                'line' => $cols[2].'-'.$cols[3].'-'.$cols[4],
            ];
        }

        return $words;
    }

    /**
     * Monta a tabela: acha a linha de cabecalho, define as colunas pela posicao
     * de cada titulo e encaixa as palavras das linhas seguintes nas colunas.
     *
     * @param  list<array{text: string, x: float, y: float, w: float, h: float, conf: float, line: string}>  $words
     */
    private function sheet(string $name, array $words): XlsxSheet
    {
        $lines = [];
        foreach ($words as $word) {
            if ($this->isNoise($word)) {
                continue;
            }
            $lines[$word['line']][] = $word;
        }
        foreach ($lines as &$line) {
            usort($line, fn (array $a, array $b): int => $a['x'] <=> $b['x']);
        }
        unset($line);
        uasort($lines, fn (array $a, array $b): int => $a[0]['y'] <=> $b[0]['y']);
        $lines = array_values($lines);

        $headerIndex = null;
        $columns = [];
        foreach (array_slice($lines, 0, self::HEADER_SEARCH_LINES, true) as $index => $line) {
            $cells = $this->cells($line);
            $fields = array_filter(array_map(fn (array $cell): ?string => StudentSpreadsheetPreview::fieldForHeader($cell['text']), $cells));
            if (in_array('name', $fields, true) && count($fields) >= 2) {
                $headerIndex = $index;
                $columns = $cells;
                break;
            }
        }

        if ($headerIndex === null) {
            // Sem cabecalho reconhecivel, a pre-visualizacao ignora a aba e avisa.
            return new XlsxSheet($name, []);
        }

        $rows = [1 => array_map(fn (array $cell): string => $cell['text'], $columns)];
        $confidence = [];
        $starts = array_map(fn (array $cell): float => $cell['x'], $columns);
        // A confianca medida e a do NOME: e o unico campo sem formato que o
        // denuncie. Data e RA ja sao recusados ou ficam pendentes quando a
        // leitura sai fora do formato, e a turma precisa bater com uma turma
        // existente. A numeracao da lista nao conta.
        $weighed = [];
        foreach ($columns as $index => $cell) {
            $field = StudentSpreadsheetPreview::fieldForHeader($cell['text']);
            $weighed[$index] = $field === 'name';
        }

        foreach (array_slice($lines, $headerIndex + 1) as $offset => $line) {
            $row = [];
            $lowest = 100.0;
            foreach ($line as $word) {
                $column = $this->columnFor($word, $starts);
                $row[$column] = isset($row[$column]) ? $row[$column].' '.$word['text'] : $word['text'];
                if ($weighed[$column] && mb_strlen($word['text']) >= 3) {
                    $lowest = min($lowest, $word['conf']);
                }
            }
            ksort($row);
            $rowNumber = $offset + 2;
            $rows[$rowNumber] = $row;
            $confidence[$rowNumber] = round($lowest, 1);
        }

        return new XlsxSheet($name, $rows, $confidence);
    }

    /**
     * Agrupa as palavras do cabecalho em celulas: espaco maior que a altura
     * da letra separa uma coluna da outra.
     *
     * @param  list<array{text: string, x: float, y: float, w: float, h: float, conf: float, line: string}>  $line
     * @return list<array{text: string, x: float}>
     */
    private function cells(array $line): array
    {
        $cells = [];
        $previousEnd = null;
        foreach ($line as $word) {
            $gap = $previousEnd === null ? INF : $word['x'] - $previousEnd;
            if ($gap > max(8.0, $word['h'] * 1.1)) {
                $cells[] = ['text' => $word['text'], 'x' => $word['x']];
            } else {
                $cells[count($cells) - 1]['text'] .= ' '.$word['text'];
            }
            $previousEnd = $word['x'] + $word['w'];
        }

        return $cells;
    }

    /** Coluna cujo titulo comeca mais perto, pela esquerda, do centro da palavra. */
    private function columnFor(array $word, array $starts): int
    {
        $center = $word['x'] + $word['w'] / 2;
        $column = 0;
        foreach ($starts as $index => $start) {
            if ($center >= $start - $word['h']) {
                $column = $index;
            }
        }

        return $column;
    }

    /** Restos das linhas da grade: barras, tracos e letras soltas mal lidas. */
    private function isNoise(array $word): bool
    {
        $text = $word['text'];
        if (preg_match('/\A[\p{P}\p{S}]+\z/u', $text) === 1) {
            return true;
        }

        // Letra minuscula solta com leitura fraca e resto de linha da grade;
        // maiuscula solta pode ser a letra da turma ("1º A") e fica.
        return $word['conf'] < 50 && mb_strlen($text) === 1 && preg_match('/\A\p{Ll}\z/u', $text) === 1;
    }

    /** @param list<string> $command */
    private function run(array $command): string
    {
        $result = Process::timeout(self::TIMEOUT_SECONDS)
            ->env(['OMP_THREAD_LIMIT' => '1', 'LC_ALL' => 'C.UTF-8'])
            ->run($command);

        if ($result->failed()) {
            $missing = str_contains($result->errorOutput(), 'not found') || $result->exitCode() === 127;
            throw new OcrReadException($missing
                ? 'O leitor de documentos nao esta instalado neste servidor (tesseract-ocr e poppler-utils).'
                : 'Nao foi possivel ler o documento. Confira se o arquivo abre normalmente e tente de novo.');
        }

        return $result->output();
    }
}
