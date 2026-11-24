<?php

namespace App\Models\Tenant;

/** Dados do aluno que só o Censo Escolar pede (registro 30 do Educacenso). */
class StudentCensus extends TenantModel
{
    public const FILIATION = [
        1 => 'Filiação informada (mãe e/ou pai, conforme a certidão)',
        0 => 'Não declarada ou ignorada',
    ];

    public const DIFFERENTIATED_LOCATIONS = [
        7 => 'Não está em área de localização diferenciada',
        1 => 'Área de assentamento',
        2 => 'Terra indígena',
        3 => 'Comunidade quilombola',
        8 => 'Área de povos e comunidades tradicionais',
    ];

    /** Países aceitos como residência pelo Educacenso (campo 51). */
    public const RESIDENCE_COUNTRIES = [
        76 => 'Brasil',
        32 => 'Argentina',
        68 => 'Bolívia',
        170 => 'Colômbia',
        328 => 'Guiana',
        254 => 'Guiana Francesa',
        600 => 'Paraguai',
        604 => 'Peru',
        740 => 'Suriname',
        858 => 'Uruguai',
        862 => 'Venezuela',
    ];

    public const BRASIL = 76;

    protected $table = 'student_census';

    protected $primaryKey = 'student_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'student_id', 'filiation', 'parent1_name', 'parent2_name', 'indigenous_people_code',
        'nationality_country_code', 'birth_municipality_code', 'residence_country_code',
        'residence_municipality_code', 'differentiated_location', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'filiation' => 'integer',
            'indigenous_people_code' => 'integer',
            'nationality_country_code' => 'integer',
            'birth_municipality_code' => 'integer',
            'residence_country_code' => 'integer',
            'residence_municipality_code' => 'integer',
            'differentiated_location' => 'integer',
        ];
    }
}
