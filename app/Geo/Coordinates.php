<?php

namespace App\Geo;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Entende coordenadas coladas do Google Maps (ou de qualquer GPS): números
 * decimais, graus/minutos/segundos e links do Google Maps. O Google, o
 * OpenStreetMap e o GPS do celular usam o mesmo sistema (WGS 84), então os
 * números valem como estão; não há conversão a fazer.
 */
class Coordinates
{
    /** Únicos endereços de link curto que podem ser abertos, para nunca consultar outro servidor. */
    private const SHORT_LINK_HOSTS = ['maps.app.goo.gl', 'goo.gl'];

    private const GOOGLE_HOST = '/(^|\.)google\.[a-z.]{2,6}$/';

    /**
     * Ponto lido do texto, ou null se o texto não é uma coordenada nem um link
     * do Google Maps (nesse caso, ele é procurado como endereço).
     *
     * @return array{lat: float, lon: float}|null
     */
    public function parse(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 2000) {
            return null;
        }

        if (preg_match('#^https?://#i', $text) === 1) {
            return $this->fromUrl($text, 0);
        }

        return $this->fromDecimal($text) ?? $this->fromDegrees($text);
    }

    /** Texto que parece um link do Google Maps, mesmo quando não deu para ler o ponto. */
    public function looksLikeMapLink(string $text): bool
    {
        $host = strtolower((string) parse_url(trim($text), PHP_URL_HOST));

        return in_array($host, self::SHORT_LINK_HOSTS, true) || preg_match(self::GOOGLE_HOST, $host) === 1;
    }

    /** @return array{lat: float, lon: float}|null */
    private function fromDecimal(string $text): ?array
    {
        // "-23.3123, -46.1234" (Google), "-23,3123 -46,1234" (vírgula decimal) ou com ponto e vírgula.
        if (preg_match('/^\(?\s*(-?\d{1,2}(?:[.,]\d+)?)\s*(?:[,;]\s*|\s+)(-?\d{1,3}(?:[.,]\d+)?)\s*\)?$/u', $text, $m) !== 1) {
            return null;
        }

        return $this->point((float) str_replace(',', '.', $m[1]), (float) str_replace(',', '.', $m[2]));
    }

    /** @return array{lat: float, lon: float}|null */
    private function fromDegrees(string $text): ?array
    {
        // 23°18'44.4"S 46°08'31.2"W, com O/L em português e aspas tipográficas.
        $part = '(\d{1,3})\s*°\s*(?:(\d{1,2}(?:[.,]\d+)?)\s*[\'′’]\s*)?(?:(\d{1,2}(?:[.,]\d+)?)\s*(?:"|″|”|\'\')\s*)?([NSLOEW])';
        if (preg_match('/^'.$part.'[\s,;]+'.$part.'$/iu', $text, $m) !== 1) {
            return null;
        }

        $first = $this->decimal($m[1], $m[2], $m[3], $m[4]);
        $second = $this->decimal($m[5], $m[6], $m[7], $m[8]);
        $firstIsLat = in_array(strtoupper($m[4]), ['N', 'S'], true);
        if ($firstIsLat === in_array(strtoupper($m[8]), ['N', 'S'], true)) {
            return null;
        }

        return $firstIsLat ? $this->point($first, $second) : $this->point($second, $first);
    }

    private function decimal(string $degrees, string $minutes, string $seconds, string $hemisphere): float
    {
        $value = (float) $degrees + (float) str_replace(',', '.', $minutes ?: '0') / 60 + (float) str_replace(',', '.', $seconds ?: '0') / 3600;

        return in_array(strtoupper($hemisphere), ['S', 'O', 'W'], true) ? -$value : $value;
    }

    /** @return array{lat: float, lon: float}|null */
    private function fromUrl(string $url, int $hops): ?array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (in_array($host, self::SHORT_LINK_HOSTS, true)) {
            return $hops < 3 ? $this->followShortLink($url, $hops) : null;
        }
        if (preg_match(self::GOOGLE_HOST, $host) !== 1) {
            return null;
        }

        $decoded = rawurldecode($url);
        // O marcador do lugar (!3d...!4d...) vale mais que o centro da tela (@...).
        foreach (['/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', '/[?&](?:q|query|ll|destination|center)=(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/', '/@(-?\d+\.\d+),(-?\d+\.\d+)/'] as $pattern) {
            if (preg_match($pattern, $decoded, $m) === 1) {
                return $this->point((float) $m[1], (float) $m[2]);
            }
        }

        return null;
    }

    /**
     * Link curto do "Compartilhar": só o endereço de destino é lido, sem abrir
     * a página. Nenhum dado do sistema é enviado ao Google.
     *
     * @return array{lat: float, lon: float}|null
     */
    private function followShortLink(string $url, int $hops): ?array
    {
        if (! str_starts_with(strtolower($url), 'https://')) {
            return null;
        }
        try {
            $response = Http::withOptions(['allow_redirects' => false])->timeout(5)->get($url);
        } catch (Throwable) {
            return null;
        }
        $location = $response->header('Location');
        if (! $response->redirect() || $location === '') {
            return null;
        }

        return $this->fromUrl($location, $hops + 1);
    }

    /** @return array{lat: float, lon: float}|null */
    private function point(float $lat, float $lon): ?array
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        return ['lat' => round($lat, 6), 'lon' => round($lon, 6)];
    }
}
