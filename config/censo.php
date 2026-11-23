<?php

/*
 * Parâmetros da conferência do Censo Escolar e do simulador de receita.
 * São estimativas: o valor final depende do Censo, do Inep e do FNDE.
 *
 * Pesos do VAAF (Fundeb): Resolução CIF nº 5/2024, mantida para 2026 pela
 * Resolução CIF nº 16/2025. Confira a cada ano na resolução vigente.
 * PNAE: Resolução CD/FNDE nº 1/2026 (valor por aluno por dia, 200 dias).
 */
return [
    // Valor anual por aluno do Fundeb no estado (fator 1,00), publicado na
    // portaria interministerial do ano. Vazio: o simulador mostra só as
    // matrículas ponderadas, sem converter em reais.
    'vaaf_base' => env('ASTUTI_FUNDEB_VAAF_BASE') !== null && env('ASTUTI_FUNDEB_VAAF_BASE') !== ''
        ? (float) env('ASTUTI_FUNDEB_VAAF_BASE')
        : null,

    'year_reference' => 2026,

    'categories' => [
        'creche_integral' => ['label' => 'Creche em tempo integral', 'weight' => 1.55, 'pnae' => 1.57],
        'creche_parcial' => ['label' => 'Creche em tempo parcial', 'weight' => 1.25, 'pnae' => 1.57],
        'pre_integral' => ['label' => 'Pré-escola em tempo integral', 'weight' => 1.50, 'pnae' => 1.57],
        'pre_parcial' => ['label' => 'Pré-escola em tempo parcial', 'weight' => 1.15, 'pnae' => 0.82],
        'fundamental_integral' => ['label' => 'Ensino Fundamental em tempo integral', 'weight' => 1.50, 'pnae' => 1.57],
        'fundamental_iniciais' => ['label' => 'Ensino Fundamental, anos iniciais (parcial)', 'weight' => 1.00, 'pnae' => 0.57],
        'fundamental_finais' => ['label' => 'Ensino Fundamental, anos finais (parcial)', 'weight' => 1.10, 'pnae' => 0.57],
        'eja' => ['label' => 'Educação de Jovens e Adultos', 'weight' => 0.80, 'pnae' => 0.57],
    ],

    'pnae_days' => 200,

    // Merenda do AEE no contraturno, somada à da matrícula comum.
    'pnae_aee' => 0.78,
];
