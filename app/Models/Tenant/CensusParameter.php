<?php

namespace App\Models\Tenant;

/** Valor anual por aluno do Fundeb (VAAF) do estado, informado pela gestão por ano. */
class CensusParameter extends TenantModel
{
    protected $fillable = ['id', 'reference_year', 'vaaf_base', 'source_note', 'updated_by'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['reference_year' => 'integer', 'vaaf_base' => 'decimal:2'];
    }

    /** Valor do ano: o informado pela gestão; sem ele, o do .env (ou null). */
    public static function vaafFor(int $year): ?float
    {
        $value = static::query()->where('reference_year', $year)->value('vaaf_base');

        return $value !== null ? (float) $value : config('censo.vaaf_base');
    }
}
