<?php

/*
 * Georreferenciamento. No desenvolvimento usamos os servidores públicos do
 * OpenStreetMap (só dados fictícios). Em produção, aponte para servidores
 * próprios da Prefeitura ou contratados com acordo de tratamento de dados,
 * para nenhum endereço de aluno sair do ambiente controlado.
 */
return [
    'geocoder_url' => env('ASTUTI_GEOCODER_URL', 'https://nominatim.openstreetmap.org/search'),
    // Endereço a partir do ponto do mapa (preenche o cadastro da escola). Vazio desliga.
    'reverse_url' => env('ASTUTI_GEOCODER_REVERSO_URL', 'https://nominatim.openstreetmap.org/reverse'),
    'tiles_url' => env('ASTUTI_MAPA_TILES_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'tiles_attribution' => env('ASTUTI_MAPA_ATRIBUICAO', '© colaboradores do OpenStreetMap'),
    // O Nominatim pede identificação da aplicação; o contato vem do .env de cada instalação.
    'user_agent' => env('ASTUTI_GEOCODER_USER_AGENT', 'AstutiGestaoEscolar/1.0'),
    // Rotas a pé pelas ruas (OSRM). Vazio desliga e a ficha mostra só a linha reta.
    // O servidor "routed-foot" calcula a pé; o OSRM ignora o perfil escrito no
    // endereço, por isso fica "driving", igual ao site do OpenStreetMap.
    'router_url' => env('ASTUTI_ROTAS_URL', 'https://routing.openstreetmap.de/routed-foot/route/v1/driving'),
    // Regra municipal: a partir de quantos km o aluno tem direito ao transporte.
    // Usa a distância pelas ruas quando o serviço de rotas responde; senão, a linha reta.
    'transport_min_km' => (float) env('ASTUTI_TRANSPORTE_DISTANCIA_MINIMA_KM', 2.0),
];
