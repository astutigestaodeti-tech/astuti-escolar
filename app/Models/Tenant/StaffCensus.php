<?php

namespace App\Models\Tenant;

/**
 * Dados do servidor que só o Censo Escolar pede (registro 30 de gestores e
 * profissionais em sala). Deficiência é dado de saúde: acesso de quem
 * gerencia a escola, auditoria só com nomes de campos.
 */
class StaffCensus extends TenantModel
{
    protected $table = 'staff_census';

    protected $primaryKey = 'staff_member_id';

    protected $keyType = 'string';

    public $incrementing = false;

    /** Campo 56. */
    public const EDUCATION_LEVELS = [
        1 => 'Não concluiu o ensino fundamental',
        2 => 'Ensino fundamental',
        7 => 'Ensino médio',
        6 => 'Educação superior',
    ];

    public const HIGHER_EDUCATION = 6;

    public const HIGH_SCHOOL = 7;

    /** Campo 57. */
    public const HIGH_SCHOOL_TYPES = [
        1 => 'Formação geral',
        2 => 'Modalidade normal (magistério)',
        3 => 'Curso técnico',
        4 => 'Magistério indígena, modalidade normal',
    ];

    /** Campos 70, 73, 76, 79, 82 e 85. */
    public const POSTGRADUATE_TYPES = [1 => 'Especialização', 2 => 'Mestrado', 3 => 'Doutorado'];

    public const MAX_COURSES = 3;

    public const MAX_POSTGRADUATES = 6;

    /** Formação continuada (campos 89 a 108; 109 é "Nenhum"). */
    public const CONTINUING_EDUCATION = [
        'creche' => ['campo' => 89, 'rotulo' => 'Creche (0 a 3 anos)'],
        'pre_escola' => ['campo' => 90, 'rotulo' => 'Pré-escola (4 e 5 anos)'],
        'alfabetizacao' => ['campo' => 91, 'rotulo' => 'Alfabetização'],
        'anos_iniciais' => ['campo' => 92, 'rotulo' => 'Anos iniciais do ensino fundamental'],
        'anos_finais' => ['campo' => 93, 'rotulo' => 'Anos finais do ensino fundamental'],
        'ensino_medio' => ['campo' => 94, 'rotulo' => 'Ensino médio'],
        'eja' => ['campo' => 95, 'rotulo' => 'Educação de jovens e adultos'],
        'educacao_especial' => ['campo' => 96, 'rotulo' => 'Educação especial'],
        'educacao_indigena' => ['campo' => 97, 'rotulo' => 'Educação indígena'],
        'educacao_campo' => ['campo' => 98, 'rotulo' => 'Educação do campo'],
        'educacao_ambiental' => ['campo' => 99, 'rotulo' => 'Educação ambiental'],
        'direitos_humanos' => ['campo' => 100, 'rotulo' => 'Educação em direitos humanos'],
        'bilingue_surdos' => ['campo' => 101, 'rotulo' => 'Educação bilíngue de surdos'],
        'tic' => ['campo' => 102, 'rotulo' => 'Educação e tecnologia de informação e comunicação (TIC)'],
        'tempo_integral' => ['campo' => 103, 'rotulo' => 'Educação integral em tempo integral'],
        'genero_diversidade' => ['campo' => 104, 'rotulo' => 'Gênero e diversidade sexual'],
        'direitos_crianca' => ['campo' => 105, 'rotulo' => 'Direitos de criança e adolescente'],
        'etnico_raciais' => ['campo' => 106, 'rotulo' => 'Relações étnico-raciais e história e cultura afro-brasileira e africana'],
        'gestao_escolar' => ['campo' => 107, 'rotulo' => 'Gestão escolar'],
        'outros' => ['campo' => 108, 'rotulo' => 'Outros'],
    ];

    /** Áreas que o campo 67 a 69 não aceita (Tabela de Áreas do Conhecimento). */
    public const AREAS_NOT_FOR_COMPLEMENT = [32, 99];

    protected $fillable = [
        'staff_member_id', 'cpf', 'birth_date', 'filiation', 'parent1_name', 'parent2_name', 'sex', 'race_color',
        'indigenous_people_code', 'nationality', 'nationality_country_code', 'birth_municipality_code', 'conditions',
        'education_level', 'high_school_type', 'higher_courses', 'pedagogical_areas', 'postgraduates',
        'no_postgraduate', 'continuing_education', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'filiation' => 'integer',
            'indigenous_people_code' => 'integer',
            'nationality_country_code' => 'integer',
            'birth_municipality_code' => 'integer',
            'conditions' => 'array',
            'education_level' => 'integer',
            'high_school_type' => 'integer',
            'higher_courses' => 'array',
            'pedagogical_areas' => 'array',
            'postgraduates' => 'array',
            'no_postgraduate' => 'boolean',
            'continuing_education' => 'array',
        ];
    }
}
