<?php

namespace App\Address;

use App\Import\Students\ImportText;
use App\Models\Tenant\PostalCode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base local de CEPs da Prefeitura: consulta e carga a partir de CSV.
 * Nada é enviado para fora do servidor.
 */
class PostalCodeBook
{
    public const FICTITIOUS_FILE = 'data/ceps/monte-sereno-ficticio.csv';

    private const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB',
        'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    /** "07500000", "07500-000" ou "07.500-000" viram "07500-000"; o resto, null. */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) === 8 ? substr($digits, 0, 5).'-'.substr($digits, 5) : null;
    }

    /** @return array{cep: string, logradouro: ?string, bairro: ?string, cidade: string, uf: string, zona: ?string}|null */
    public function find(mixed $value): ?array
    {
        $code = self::normalize($value);
        if ($code === null) {
            return null;
        }

        $row = PostalCode::query()->find($code);
        if ($row === null) {
            return null;
        }

        return [
            'cep' => $row->code,
            'logradouro' => $row->street,
            'bairro' => $row->district,
            'cidade' => $row->city,
            'uf' => $row->state,
            'zona' => $row->residence_zone,
        ];
    }

    /**
     * Carrega um CSV com cabeçalho cep;logradouro;bairro;cidade;uf[;zona]
     * (UTF-8 ou Windows-1252, ponto e vírgula ou vírgula). Atualiza CEPs já existentes.
     *
     * @return array{lidos: int, gravados: int}
     */
    public function import(string $path, string $source): array
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível abrir o arquivo de CEPs: '.$path);
        }

        try {
            $header = fgets($stream);
            if ($header === false) {
                throw new RuntimeException('O arquivo de CEPs está vazio.');
            }
            $delimiter = substr_count($header, ';') >= substr_count($header, ',') ? ';' : ',';
            $columns = array_flip(array_map(
                fn (string $cell): string => ImportText::key($this->utf8($cell)),
                str_getcsv(preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header, $delimiter, '"', ''),
            ));
            foreach (['cep', 'cidade', 'uf'] as $required) {
                if (! isset($columns[$required])) {
                    throw new RuntimeException('O arquivo de CEPs precisa das colunas cep, logradouro, bairro, cidade e uf.');
                }
            }

            $rows = [];
            $line = 1;
            while (($raw = fgets($stream)) !== false) {
                $line++;
                $cells = array_map(fn (?string $cell): string => trim($this->utf8((string) $cell)), str_getcsv($raw, $delimiter, '"', ''));
                if (implode('', $cells) === '') {
                    continue;
                }

                $code = self::normalize($cells[$columns['cep']] ?? '');
                $state = strtoupper($cells[$columns['uf']] ?? '');
                $city = $cells[$columns['cidade']] ?? '';
                if ($code === null || ! in_array($state, self::UFS, true) || $city === '') {
                    throw new RuntimeException('Linha '.$line.' do arquivo de CEPs inválida (CEP, cidade ou UF).');
                }

                $zone = match (ImportText::key($cells[$columns['zona'] ?? -1] ?? '')) {
                    'urbana', 'urban' => 'urban',
                    'rural' => 'rural',
                    default => null,
                };
                $rows[$code] = [
                    'code' => $code,
                    'street' => $this->optional($cells[$columns['logradouro'] ?? -1] ?? null, 180),
                    'district' => $this->optional($cells[$columns['bairro'] ?? -1] ?? null, 120),
                    'city' => mb_substr($city, 0, 120),
                    'state' => $state,
                    'residence_zone' => $zone,
                    'source' => mb_substr($source, 0, 60),
                    'updated_at' => now(),
                ];
            }
        } finally {
            fclose($stream);
        }

        DB::connection('tenant')->transaction(function () use ($rows): void {
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                PostalCode::query()->upsert($chunk, ['code'], ['street', 'district', 'city', 'state', 'residence_zone', 'source', 'updated_at']);
            }
        });

        return ['lidos' => $line - 1, 'gravados' => count($rows)];
    }

    private function optional(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function utf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : (string) iconv('Windows-1252', 'UTF-8//IGNORE', $value);
    }
}
