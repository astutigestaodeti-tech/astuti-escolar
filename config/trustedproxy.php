<?php

use App\Support\TrustedProxyAddresses;

return [
    // Lista vazia nao confia em cabecalhos encaminhados. Nao usar "*".
    'proxies' => TrustedProxyAddresses::parse((string) env('TRUSTED_PROXIES', '')),
];
