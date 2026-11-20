<?php

// As credenciais por municipio devem vir de configuracao privada do ambiente.
// A excecao abaixo existe SOMENTE para o cenario ficticio de desenvolvimento.
$connections = [];
$platform = config('database.connections.platform', []);

if (app()->environment('local')
    && is_array($platform)
    && ($platform['driver'] ?? null) === 'mariadb'
    && ($platform['database'] ?? null) === 'astuti_platform_dev'
    && in_array(strtolower((string) ($platform['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true)
    && empty($platform['url'])) {
    $connections['monte_sereno'] = array_replace($platform, [
        'database' => 'astuti_monte_sereno_dev',
    ]);
}

return ['connections' => $connections];
