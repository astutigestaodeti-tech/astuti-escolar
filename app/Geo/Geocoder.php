<?php

namespace App\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Converte endereço em coordenada pelo serviço configurado (Nominatim,
 * do OpenStreetMap). Uma consulta por clique, com cache, e só no Brasil.
 * Falha de rede nunca derruba a tela: devolve null.
 */
class Geocoder
{
    /** @return array{lat: float, lon: float}|null */
    public function locate(?string $street, ?string $number, ?string $district, ?string $city, ?string $state, ?string $postalCode): ?array
    {
        $parts = array_values(array_filter([
            trim(implode(' ', array_filter([$street, $number]))) ?: null,
            $district, $city, $state, $postalCode, 'Brasil',
        ], fn ($part): bool => is_string($part) && trim($part) !== ''));
        if (count($parts) < 3) {
            return null;
        }
        $query = implode(', ', $parts);

        return Cache::remember('geo:'.sha1(mb_strtolower($query)), now()->addDays(30), function () use ($query): ?array {
            try {
                $response = Http::withHeaders(['User-Agent' => config('geo.user_agent')])
                    ->acceptJson()->timeout(5)
                    ->get(config('geo.geocoder_url'), ['q' => $query, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'br']);
                $first = $response->successful() ? ($response->json()[0] ?? null) : null;
            } catch (Throwable) {
                return null;
            }
            if (! is_array($first) || ! is_numeric($first['lat'] ?? null) || ! is_numeric($first['lon'] ?? null)) {
                return null;
            }

            return ['lat' => round((float) $first['lat'], 6), 'lon' => round((float) $first['lon'], 6)];
        });
    }
}
