<?php

/*
 * Data de corte da fila de educação infantil. Cada VPS atende uma Prefeitura.
 * Definir ASTUTI_LISTA_ESPERA_CORTE=2027-03-31 para o ano de ingresso desejado.
 * Alterar a data de corte não recalcula nem reordena pedidos já protocolados:
 * eles preservam o ano, a etapa e o corte da inscrição.
 */
return [
    'cutoff_date' => env('ASTUTI_LISTA_ESPERA_CORTE', sprintf(
        '%04d-03-31',
        (int) date('Y') + ((int) date('n') >= 10 ? 1 : 0),
    )),
];
