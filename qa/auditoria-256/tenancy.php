<?php
// Configuração exclusiva da cópia descartável do runner; não vai para a main.
if (getenv('GITHUB_ACTIONS') !== 'true' || config('app.env') !== 'testing') {
    throw new RuntimeException('QA somente em runner descartável.');
}
$platform = config('database.connections.platform');
if ($platform['host'] !== '127.0.0.1' || $platform['database'] !== 'astuti_platform_test'
    || getenv('TENANCY_TEST_DB_A') !== 'astuti_tenant_a_test'
    || getenv('TENANCY_TEST_DB_B') !== 'astuti_tenant_b_test') {
    throw new RuntimeException('Bancos exclusivos de teste exigidos.');
}
return [
    'connections' => [
        'municipio_a' => array_replace($platform, ['database' => 'astuti_tenant_a_test']),
        'municipio_b' => array_replace($platform, ['database' => 'astuti_tenant_b_test']),
    ],
    'deployed_db_key' => null,
];
