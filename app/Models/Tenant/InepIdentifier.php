<?php

namespace App\Models\Tenant;

/** Código devolvido pelo Educacenso para uma pessoa, turma ou matrícula. */
class InepIdentifier extends TenantModel
{
    public const STUDENT = 'student';

    public const STAFF_MEMBER = 'staff_member';

    public const SCHOOL_CLASS = 'school_class';

    public const ENROLLMENT = 'student_enrollment';

    public const KINDS = [
        self::STUDENT => 'ID Inep do aluno',
        self::STAFF_MEMBER => 'ID Inep do servidor',
        self::SCHOOL_CLASS => 'Código Inep da turma',
        self::ENROLLMENT => 'Código da matrícula',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'kind', 'entity_id', 'inep_code', 'census_year', 'imported_by'];

    protected function casts(): array
    {
        return ['census_year' => 'integer'];
    }
}
