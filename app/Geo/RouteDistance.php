<?php

namespace App\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Distância pelas ruas, a pé, pelo serviço de rotas configurado (OSRM, do
 * OpenStreetMap). É a distância que a família percorre de fato e a que as
 * regras de transporte escolar costumam usar.
 *
 * O resultado fica em cache por par de pontos, então cada casa é consultada
 * uma vez. Falha de rede nunca derruba a tela: devolve null e guarda a falha
 * por alguns minutos, para a ficha não ficar lenta com o serviço fora do ar.
 */
class RouteDistance
{
    public function km(float $fromLat, float $fromLon, float $toLat, float $toLon): ?float
    {
        $url = rtrim((string) config('geo.router_url'), '/');
        if ($url === '') {
            return null;
        }

        $points = sprintf('%.6F,%.6F;%.6F,%.6F', $fromLon, $fromLat, $toLon, $toLat);
        $key = 'rota:'.sha1($url.'|'.$points);
        $cached = Cache::get($key);
        if (is_float($cached) || is_int($cached)) {
            return (float) $cached;
        }
        if ($cached === false) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => config('geo.user_agent')])
                ->acceptJson()->timeout(4)
                ->get($url.'/'.$points, ['overview' => 'false', 'alternatives' => 'false', 'steps' => 'false']);
            $meters = $response->successful() && $response->json('code') === 'Ok'
                ? $response->json('routes.0.distance')
                : null;
        } catch (Throwable) {
            $meters = null;
        }

        if (! is_numeric($meters) || $meters < 0) {
            Cache::put($key, false, now()->addMinutes(10));

            return null;
        }

        $km = round((float) $meters / 1000, 3);
        Cache::put($key, $km, now()->addDays(180));

        return $km;
    }
}
