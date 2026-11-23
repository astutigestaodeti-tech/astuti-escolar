<?php

return [
    // Referências para sugestão de fechamento, nunca aprovação automática.
    'minimum_grade' => (float) env('ASTUTI_MEDIA_MINIMA', 6.0),
    'minimum_attendance' => (float) env('ASTUTI_FREQUENCIA_MINIMA', 75.0),
    // UF da rede emissora, se configurada. Não presumir o estado do aluno.
    'municipality_state' => env('ASTUTI_MUNICIPALITY_UF'),
];
