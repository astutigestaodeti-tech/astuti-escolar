<?php

// As credenciais por municipio devem vir de configuracao privada do ambiente.
// A excecao abaixo existe SOMENTE para o cenario ficticio de desenvolvimento.
$connections = [];
$platform = config('database.connections.platform', []);

// Durante o carregamento da configuracao, o servico app('env') ainda nao
// esta disponivel. Consultar somente app.env, ja carregado neste ponto.
if (config('app.env') === 'local'
    && is_array($platform)
    && ($platform['driver'] ?? null) === 'pgsql'
    && ($platform['database'] ?? null) === 'astuti_platform_dev'
    && in_array(strtolower((string) ($platform['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true)
    && empty($platform['url'])) {
    $connections['monte_sereno'] = array_replace($platform, [
        'database' => 'astuti_monte_sereno_dev',
    ]);
}

// Em producao, a configuracao privada devera fornecer somente a base daquela prefeitura.
return [
    'connections' => $connections,
    'deployed_db_key' => env('ASTUTI_MUNICIPALITY_DB_KEY'),
];
