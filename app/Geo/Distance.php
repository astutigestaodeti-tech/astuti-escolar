<?php

namespace App\Geo;

/** Distância em linha reta (fórmula de haversine), em quilômetros. */
final class Distance
{
    private const EARTH_RADIUS_KM = 6371.0088;

    public static function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    /** "850 m" abaixo de 1 km; "3,4 km" acima. */
    public static function label(float $km): string
    {
        return $km < 1
            ? (string) (int) (round($km * 1000 / 10) * 10).' m'
            : number_format($km, 1, ',', '.').' km';
    }

    /** Situação do transporte comparando a distância com a regra municipal. */
    public static function transportHint(float $km, ?string $transportStatus, float $minKm): ?string
    {
        $far = $km >= $minKm;
        $uses = $transportStatus === 'active';

        return match (true) {
            $far && ! $uses => 'Mora a mais de '.self::label($minKm).' em linha reta e não está no transporte: confira se a família precisa.',
            ! $far && $uses => 'Está no transporte e mora a menos de '.self::label($minKm).' em linha reta: confira se há motivo (deficiência, trajeto perigoso).',
            default => null,
        };
    }
}
