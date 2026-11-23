<?php

return [
    // Referências para sugestão de fechamento, nunca aprovação automática.
    'minimum_grade' => (float) env('ASTUTI_MEDIA_MINIMA', 6.0),
    'minimum_attendance' => (float) env('ASTUTI_FREQUENCIA_MINIMA', 75.0),
];
