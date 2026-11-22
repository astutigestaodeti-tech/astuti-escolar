<?php

namespace App\Support;

use InvalidArgumentException;

final class TrustedProxyAddresses
{
    /**
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $proxies = [];

        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            $parts = explode('/', $entry);

            if (count($parts) > 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException('TRUSTED_PROXIES deve conter somente enderecos IP ou redes CIDR validas.');
            }

            if (count($parts) === 2) {
                $limit = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;

                if (! preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $parts[1]) || (int) $parts[1] > $limit) {
                    throw new InvalidArgumentException('TRUSTED_PROXIES contem uma mascara CIDR invalida.');
                }
            }

            $proxies[] = $entry;
        }

        return array_values(array_unique($proxies));
    }
}
