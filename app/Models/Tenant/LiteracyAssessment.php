<?php

namespace App\Models\Tenant;

class LiteracyAssessment extends TenantModel
{
    public const WRITING = [
        'pre_syllabic' => 'Pré-silábica',
        'syllabic_no_sound' => 'Silábica sem valor sonoro',
        'syllabic_sound' => 'Silábica com valor sonoro',
        'syllabic_alphabetic' => 'Silábico-alfabética',
        'alphabetic' => 'Alfabética',
    ];

    public const READING = [
        'none' => 'Não lê',
        'words' => 'Lê palavras',
        'sentences' => 'Lê frases',
        'fluent_texts' => 'Lê textos com fluência',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'school_class_id', 'student_enrollment_id', 'term', 'writing', 'reading', 'recorded_by', 'updated_by'];

    protected function casts(): array
    {
        return ['term' => 'integer'];
    }
}
