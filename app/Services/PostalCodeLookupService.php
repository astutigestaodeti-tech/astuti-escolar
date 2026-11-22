<?php

namespace App\Services;

use App\Address\BrazilianStates;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PostalCodeLookupService
{
    /** @return array{0: array<string, mixed>, 1: int} */
    public function lookup(string $digits): array
    {
        $postalCode = substr($digits, 0, 5).'-'.substr($digits, 5);
        $cache = DB::connection('tenant')->table('postal_code_cache')->where('postal_code', $postalCode)->first();

        if ($cache !== null && Carbon::parse($cache->fetched_at)->greaterThanOrEqualTo(now()->subDays(30))) {
            return [$this->cachedResponse($cache, 'cache'), 200];
        }

        try {
            $response = Http::acceptJson()->timeout(3)->get('https://viacep.com.br/ws/'.$digits.'/json/');
        } catch (ConnectionException) {
            return $this->unavailable($cache);
        }

        if (! $response->successful()) {
            return $this->unavailable($cache);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return $this->unavailable($cache);
        }

        if (($payload['erro'] ?? false) === true) {
            return [['message' => 'CEP não encontrado. Preencha o endereço manualmente.'], 404];
        }

        $providerCode = $this->part($payload, 'cep', 9);
        $city = $this->part($payload, 'localidade', 120);
        $state = $this->part($payload, 'uf', 2);

        if ($providerCode === null || str_replace('-', '', $providerCode) !== $digits
            || $city === null || $state === null || ! array_key_exists($state, BrazilianStates::NAMES)) {
            return $this->unavailable($cache);
        }

        $address = [
            'postal_code' => $postalCode,
            'street' => $this->part($payload, 'logradouro', 180),
            'district' => $this->part($payload, 'bairro', 120),
            'city' => $city,
            'state' => $state,
        ];

        DB::connection('tenant')->table('postal_code_cache')->updateOrInsert(
            ['postal_code' => $postalCode],
            [
                'street' => $address['street'],
                'district' => $address['district'],
                'city' => $address['city'],
                'state' => $address['state'],
                'fetched_at' => now(),
            ],
        );

        return [$address + ['source' => 'online'], 200];
    }

    private function part(array $payload, string $key, int $limit): ?string
    {
        $raw = $payload[$key] ?? null;
        if (! is_string($raw)) {
            return null;
        }

        $value = trim($raw);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    /** @return array{0: array<string, mixed>, 1: int} */
    private function unavailable(?object $cache): array
    {
        if ($cache !== null) {
            return [$this->cachedResponse($cache, 'stale'), 200];
        }

        return [['message' => 'Consulta de CEP indisponível. Preencha o endereço manualmente.'], 503];
    }

    /** @return array<string, mixed> */
    private function cachedResponse(object $cache, string $source): array
    {
        return [
            'postal_code' => $cache->postal_code,
            'street' => $cache->street,
            'district' => $cache->district,
            'city' => $cache->city,
            'state' => $cache->state,
            'source' => $source,
        ];
    }
}
