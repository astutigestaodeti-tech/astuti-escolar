<?php

namespace App\Models\Tenant;

use App\Educacenso\CodigoCenso;

/** Dados de uma turma para o Censo Escolar (registro 20 do Educacenso). */
class SchoolClassCensus extends TenantModel
{
    public const DAYS = [
        'sunday' => 'Domingo',
        'monday' => 'Segunda-feira',
        'tuesday' => 'Terça-feira',
        'wednesday' => 'Quarta-feira',
        'thursday' => 'Quinta-feira',
        'friday' => 'Sexta-feira',
        'saturday' => 'Sábado',
    ];

    protected $table = 'school_class_census';

    protected $primaryKey = 'school_class_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'school_class_id', 'inep_stage_code', 'mediation', 'class_type',
        'schedule_sunday', 'schedule_monday', 'schedule_tuesday', 'schedule_wednesday',
        'schedule_thursday', 'schedule_friday', 'schedule_saturday',
        'complementary_activities', 'differentiated_location', 'special_class',
        'organization', 'alternance', 'curricular_areas', 'bilingual_deaf', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'inep_stage_code' => 'integer',
            'mediation' => 'integer',
            'class_type' => 'integer',
            'differentiated_location' => 'integer',
            'organization' => 'integer',
            'special_class' => 'boolean',
            'alternance' => 'boolean',
            'bilingual_deaf' => 'boolean',
            'complementary_activities' => 'array',
            'curricular_areas' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // O código é criado uma vez e nunca muda: o Inep o devolve no ano seguinte.
        static::creating(function (self $census): void {
            $census->census_code ??= CodigoCenso::novo();
        });
        static::updating(function (self $census): void {
            if ($census->isDirty('census_code')) {
                throw new \LogicException('O código da turma no Censo não pode mudar.');
            }
        });
    }
}
