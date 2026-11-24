<?php

namespace App\Models\Tenant;

/**
 * Educação especial no padrão do Censo Escolar. Dado sensível de criança:
 * só a gestão da escola vê e edita, e a auditoria registra quais campos
 * mudaram, nunca os valores.
 */
class StudentSpecialEducation extends TenantModel
{
    protected $table = 'student_special_education';

    /** Categorias do Censo Escolar (deficiência, TEA e altas habilidades). */
    public const CONDITIONS = [
        'cegueira' => 'Cegueira',
        'baixa_visao' => 'Baixa visão',
        'visao_monocular' => 'Visão monocular',
        'surdez' => 'Surdez',
        'deficiencia_auditiva' => 'Deficiência auditiva',
        'surdocegueira' => 'Surdocegueira',
        'deficiencia_fisica' => 'Deficiência física',
        'deficiencia_intelectual' => 'Deficiência intelectual',
        'tea' => 'Transtorno do espectro autista (TEA)',
        'altas_habilidades' => 'Altas habilidades ou superdotação',
    ];

    /** Recursos para provas e avaliações externas (Saeb, SARESP). */
    public const EXAM_RESOURCES = [
        'ledor' => 'Auxílio ledor',
        'transcricao' => 'Auxílio para transcrição',
        'libras' => 'Tradutor-intérprete de Libras',
        'guia_interprete' => 'Guia-intérprete',
        'leitura_labial' => 'Leitura labial',
        'prova_ampliada' => 'Prova ampliada (fonte 18)',
        'prova_superampliada' => 'Prova superampliada (fonte 24)',
        'braille' => 'Prova em Braille',
        'tempo_adicional' => 'Tempo adicional',
        'cd_audio' => 'CD com áudio para deficiente visual',
        'portugues_segunda_lingua' => 'Prova de Língua Portuguesa como segunda língua (surdos)',
        'video_libras' => 'Prova em vídeo em Libras',
        'material_braille' => 'Material didático em Braille',
    ];

    /** Transtornos que impactam a aprendizagem (registro 30, campos 30 a 35). */
    public const LEARNING_DISORDERS = [
        'discalculia' => 'Discalculia ou outro transtorno da matemática e raciocínio lógico',
        'disgrafia' => 'Disgrafia, disortografia ou outro transtorno da escrita e ortografia',
        'dislalia' => 'Dislalia ou outro transtorno da linguagem e comunicação',
        'dislexia' => 'Dislexia',
        'tdah' => 'Transtorno do déficit de atenção com hiperatividade (TDAH)',
        'tpac' => 'Transtorno do processamento auditivo central (TPAC)',
    ];

    /** Atividades do AEE (registro 60, campos 10 a 20). */
    public const AEE_ACTIVITIES = [
        'funcoes_cognitivas' => 'Desenvolvimento de funções cognitivas',
        'vida_autonoma' => 'Desenvolvimento de vida autônoma',
        'enriquecimento' => 'Enriquecimento curricular',
        'informatica' => 'Ensino da informática acessível',
        'libras' => 'Ensino da Língua Brasileira de Sinais (Libras)',
        'portugues_segunda_lingua' => 'Ensino da Língua Portuguesa como segunda língua',
        'soroban' => 'Ensino das técnicas do cálculo no Soroban',
        'braille' => 'Ensino do Sistema Braille',
        'orientacao_mobilidade' => 'Ensino de técnicas para orientação e mobilidade',
        'caa' => 'Ensino de uso da Comunicação Alternativa e Aumentativa (CAA)',
        'recursos_opticos' => 'Ensino de uso de recursos ópticos e não ópticos',
    ];

    /** Campo do registro 30 de cada categoria, transtorno e recurso (leiaute 2026). */
    public const CENSUS_FIELDS = [
        'conditions' => [
            'cegueira' => 18, 'baixa_visao' => 19, 'visao_monocular' => 20, 'surdez' => 21, 'deficiencia_auditiva' => 22,
            'surdocegueira' => 23, 'deficiencia_fisica' => 24, 'deficiencia_intelectual' => 25, 'tea' => 27, 'altas_habilidades' => 28,
        ],
        'learning_disorders' => ['discalculia' => 30, 'disgrafia' => 31, 'dislalia' => 32, 'dislexia' => 33, 'tdah' => 34, 'tpac' => 35],
        'exam_resources' => [
            'ledor' => 36, 'transcricao' => 37, 'guia_interprete' => 38, 'libras' => 39, 'leitura_labial' => 40,
            'prova_ampliada' => 41, 'prova_superampliada' => 42, 'cd_audio' => 43, 'portugues_segunda_lingua' => 44,
            'video_libras' => 45, 'material_braille' => 46, 'braille' => 47, 'tempo_adicional' => 48,
        ],
    ];

    /** Campo do registro 60 de cada atividade de AEE (leiaute 2026). */
    public const AEE_ACTIVITY_FIELDS = [
        'funcoes_cognitivas' => 10, 'vida_autonoma' => 11, 'enriquecimento' => 12, 'informatica' => 13, 'libras' => 14,
        'portugues_segunda_lingua' => 15, 'soroban' => 16, 'braille' => 17, 'orientacao_mobilidade' => 18, 'caa' => 19, 'recursos_opticos' => 20,
    ];

    public const AEE_STATUSES = [
        'participa' => 'Frequenta o AEE',
        'aguardando' => 'Aguardando vaga no AEE',
        'nao_participa' => 'Não frequenta (família optou ou não precisa)',
    ];

    public $incrementing = false;

    protected $primaryKey = 'student_id';

    protected $keyType = 'string';

    protected $fillable = [
        'student_id', 'conditions', 'learning_disorders', 'exam_resources', 'aee_activities', 'aee_status', 'aee_school_id',
        'aee_started_on', 'support_professional', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'learning_disorders' => 'array',
            'exam_resources' => 'array',
            'aee_activities' => 'array',
            'aee_started_on' => 'date',
            'support_professional' => 'boolean',
        ];
    }

    /** @return list<string> */
    public function conditionLabels(): array
    {
        return array_values(array_map(fn (string $key): string => self::CONDITIONS[$key] ?? $key, $this->conditions ?? []));
    }

    /** @return list<string> */
    public function disorderLabels(): array
    {
        return array_values(array_map(fn (string $key): string => self::LEARNING_DISORDERS[$key] ?? $key, $this->learning_disorders ?? []));
    }

    /** @return list<string> */
    public function resourceLabels(): array
    {
        return array_values(array_map(fn (string $key): string => self::EXAM_RESOURCES[$key] ?? $key, $this->exam_resources ?? []));
    }

    public function aeeLabel(): string
    {
        return self::AEE_STATUSES[$this->aee_status] ?? 'Não informado';
    }
}
