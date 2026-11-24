<?php

namespace App\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Converte endereço em coordenada pelo serviço configurado (Nominatim,
 * do OpenStreetMap), com cache e só no Brasil. Falha de rede nunca derruba
 * a tela: devolve null.
 *
 * O OpenStreetMap não entende abreviações como "R. Pref." e costuma não ter
 * o número de todas as casas. Por isso o endereço é escrito por extenso e
 * procurado em etapas: com número, só a rua na cidade e, por fim, em texto
 * livre. O resultado diz se o número foi achado ou só a rua.
 */
class Geocoder
{
    /** Abreviações comuns nos endereços brasileiros (e no Google Maps). */
    private const ABBREVIATIONS = [
        'r' => 'Rua', 'av' => 'Avenida', 'al' => 'Alameda', 'tv' => 'Travessa', 'trav' => 'Travessa',
        'estr' => 'Estrada', 'est' => 'Estrada', 'rod' => 'Rodovia', 'pca' => 'Praça', 'pça' => 'Praça', 'lgo' => 'Largo',
        'pref' => 'Prefeito', 'dr' => 'Doutor', 'dra' => 'Doutora', 'prof' => 'Professor', 'profa' => 'Professora', 'profª' => 'Professora',
        'ver' => 'Vereador', 'cel' => 'Coronel', 'cap' => 'Capitão', 'gen' => 'General', 'eng' => 'Engenheiro', 'pe' => 'Padre',
        'sto' => 'Santo', 'sta' => 'Santa', 'des' => 'Desembargador', 'dep' => 'Deputado', 'sen' => 'Senador', 'gov' => 'Governador',
        'pres' => 'Presidente', 'maj' => 'Major', 'ten' => 'Tenente', 'mal' => 'Marechal', 'alm' => 'Almirante', 'com' => 'Comendador',
        'comend' => 'Comendador', 'sgt' => 'Sargento', 'cmte' => 'Comandante', 'fr' => 'Frei', 'jd' => 'Jardim', 'pq' => 'Parque', 'vl' => 'Vila',
    ];

    /**
     * Endereço em campos separados (cadastro do aluno ou endereço do Google).
     *
     * @return array{lat: float, lon: float, precision: string}|null precision: "numero" ou "rua"
     */
    public function locate(?string $street, ?string $number, ?string $district, ?string $city, ?string $state, ?string $postalCode): ?array
    {
        $street = $this->expand($street);
        $city = $this->clean($city);
        $number = $this->clean($number);
        if ($street === null || $city === null) {
            return null;
        }
        $state = $this->clean($state);
        $district = $this->expand($district);

        $structured = fn (string $streetText): array => array_filter([
            'street' => $streetText, 'city' => $city, 'state' => $state, 'country' => 'Brasil',
        ], fn (?string $value): bool => $value !== null);

        if ($number !== null && ($point = $this->query($structured($number.' '.$street))) !== null) {
            return [...$point, 'precision' => 'numero'];
        }
        if (($point = $this->query($structured($street))) !== null) {
            return [...$point, 'precision' => $number === null ? 'numero' : 'rua'];
        }
        $free = implode(', ', array_filter([$street, $district, $city, $state, 'Brasil'], fn (?string $part): bool => $part !== null));
        if (($point = $this->query(['q' => $free])) !== null) {
            return [...$point, 'precision' => 'rua'];
        }

        // A rua não está no mapa (comum em cidades pequenas): mostra o bairro
        // ou a cidade, e a pessoa arrasta o marcador até o lugar certo.
        if ($district !== null && ($point = $this->query(['q' => implode(', ', array_filter([$district, $city, $state, 'Brasil']))])) !== null) {
            return [...$point, 'precision' => 'bairro'];
        }
        if (($point = $this->query(array_filter(['city' => $city, 'state' => $state, 'country' => 'Brasil']))) !== null) {
            return [...$point, 'precision' => 'cidade'];
        }

        return null;
    }

    /**
     * Texto digitado ou colado, como o endereço copiado do Google Maps:
     * "R. Pref. José Basílio Alvarenga, 856 - Jardim Monte Serrat, Santa Isabel - SP, 07500-000".
     *
     * @return array{lat: float, lon: float, precision: string}|null
     */
    public function search(string $text): ?array
    {
        // CEP genérico de cidade (07500-000) atrapalha mais do que ajuda.
        $text = trim((string) preg_replace('/,?\s*\b\d{5}-?\d{3}\b/', '', $text), ' ,-');
        if ($text === '') {
            return null;
        }

        // Rua, número - bairro, cidade - UF
        if (preg_match('/^(?<street>[^,]+),\s*(?<number>(?:\d+[A-Za-z]?|s\/?n\.?|km\s*[\d,.]+))\s*(?:-\s*(?<district>[^,]+))?,\s*(?<city>[^,]+?)\s*-\s*(?<state>[A-Za-z]{2})$/iu', $text, $m) === 1) {
            $number = preg_match('/^\d/', $m['number']) === 1 ? $m['number'] : null;

            return $this->locate($m['street'], $number, $m['district'] ?? null, $m['city'], strtoupper($m['state']), null);
        }

        // Formato do próprio cadastro: "Rua X 240, Bairro, Cidade/UF" ou "..., Cidade, UF".
        if (preg_match('/^(?<street>[^,]+?)(?:\s+(?<number>\d+[A-Za-z]?))?(?:,\s*(?<district>[^,\/]+))?,\s*(?<city>[^,\/]+?)\s*[\/,]\s*(?<state>[A-Za-z]{2})$/u', $text, $m) === 1) {
            return $this->locate($m['street'], ($m['number'] ?? '') !== '' ? $m['number'] : null, ($m['district'] ?? '') !== '' ? $m['district'] : null, $m['city'], strtoupper($m['state']), null);
        }

        $expanded = $this->expand($text);
        if ($expanded === null) {
            return null;
        }
        $point = $this->query(['q' => $expanded.', Brasil']);

        return $point === null ? null : [...$point, 'precision' => 'numero'];
    }

    /** Abreviações por extenso: "R. Pref. José" vira "Rua Prefeito José". */
    public function expand(?string $text): ?string
    {
        $text = $this->clean($text);
        if ($text === null) {
            return null;
        }

        return $this->clean((string) preg_replace_callback('/(?<![\p{L}\d])([\p{L}ª]{1,6})(\.|(?=\s))/u', function (array $m): string {
            $key = mb_strtolower($m[1]);
            // Sem ponto, só expande o que não pode ser palavra comum ("Dr Silva", "Av Brasil").
            if (! isset(self::ABBREVIATIONS[$key]) || ($m[2] === '' && in_array($key, ['com', 'des', 'sen', 'pe', 'ver', 'est', 'gen', 'mal', 'fr', 'al', 'r'], true))) {
                return $m[0];
            }

            return self::ABBREVIATIONS[$key];
        }, $text));
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, string>  $params
     * @return array{lat: float, lon: float}|null
     */
    /**
     * Endereço de um ponto do mapa (geocodificação reversa), para preencher o
     * cadastro da escola sem digitar. Sugestão: a pessoa confere e salva.
     *
     * @return array{street: ?string, number: ?string, district: ?string, city: ?string, state: ?string, postal_code: ?string}|null
     */
    public function reverse(float $lat, float $lon): ?array
    {
        $url = (string) config('geo.reverse_url');
        if ($url === '') {
            return null;
        }
        $key = 'geo:reverso:'.sha1($url.sprintf('|%.5F,%.5F', $lat, $lon));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => config('geo.user_agent')])
                ->acceptJson()->timeout(5)
                ->get($url, ['lat' => $lat, 'lon' => $lon, 'format' => 'jsonv2', 'zoom' => 18, 'addressdetails' => 1]);
            $address = $response->successful() ? $response->json('address') : null;
        } catch (Throwable) {
            return null;
        }
        if (! is_array($address) || ($address['country_code'] ?? 'br') !== 'br') {
            return null;
        }

        $state = null;
        if (preg_match('/^BR-([A-Z]{2})$/', (string) ($address['ISO3166-2-lvl4'] ?? ''), $match) === 1) {
            $state = $match[1];
        }
        $postal = preg_replace('/\D/', '', (string) ($address['postcode'] ?? ''));
        $result = [
            'street' => $address['road'] ?? $address['pedestrian'] ?? null,
            'number' => $address['house_number'] ?? null,
            'district' => $address['suburb'] ?? $address['neighbourhood'] ?? $address['quarter'] ?? $address['city_district'] ?? null,
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null,
            'state' => $state,
            'postal_code' => strlen($postal) === 8 ? substr($postal, 0, 5).'-'.substr($postal, 5) : null,
        ];
        if ($result['street'] === null && $result['city'] === null) {
            return null;
        }
        Cache::put($key, $result, now()->addDays(30));

        return $result;
    }

    private function query(array $params): ?array
    {
        $key = 'geo:'.sha1(mb_strtolower(json_encode($params, JSON_UNESCAPED_UNICODE)));
        $cached = Cache::get($key);
        if (is_array($cached) || $cached === false) {
            return $cached ?: null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => config('geo.user_agent')])
                ->acceptJson()->timeout(5)
                ->get(config('geo.geocoder_url'), [...$params, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'br']);
            if (! $response->successful()) {
                // Serviço fora do ar: não guarda a falha, para tentar de novo depois.
                return null;
            }
            $first = $response->json()[0] ?? null;
        } catch (Throwable) {
            return null;
        }

        $point = is_array($first) && is_numeric($first['lat'] ?? null) && is_numeric($first['lon'] ?? null)
            ? ['lat' => round((float) $first['lat'], 6), 'lon' => round((float) $first['lon'], 6)]
            : null;
        Cache::put($key, $point ?? false, now()->addDays(30));

        return $point;
    }
}
