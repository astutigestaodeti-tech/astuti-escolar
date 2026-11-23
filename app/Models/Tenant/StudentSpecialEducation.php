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
        'student_id', 'conditions', 'exam_resources', 'aee_status', 'aee_school_id',
        'aee_started_on', 'support_professional', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'exam_resources' => 'array',
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
    public function resourceLabels(): array
    {
        return array_values(array_map(fn (string $key): string => self::EXAM_RESOURCES[$key] ?? $key, $this->exam_resources ?? []));
    }

    public function aeeLabel(): string
    {
        return self::AEE_STATUSES[$this->aee_status] ?? 'Não informado';
    }
}
