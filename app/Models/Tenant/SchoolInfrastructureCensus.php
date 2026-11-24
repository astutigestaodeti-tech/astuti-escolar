<?php

namespace App\Models\Tenant;

/** Questionário anual da escola para o registro 10 do Educacenso. */
class SchoolInfrastructureCensus extends TenantModel
{
    protected $table = 'school_infrastructure_census';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'school_id', 'census_year', 'answers', 'updated_by'];

    protected function casts(): array
    {
        return ['census_year' => 'integer', 'answers' => 'object'];
    }
}
