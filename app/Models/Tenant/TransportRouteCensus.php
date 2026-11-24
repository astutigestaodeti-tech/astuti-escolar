<?php

namespace App\Models\Tenant;

/** Poder público e veículos de uma rota de transporte, para o Censo (registro 60). */
class TransportRouteCensus extends TenantModel
{
    public const PUBLIC_POWERS = [2 => 'Municipal', 1 => 'Estadual'];

    /** Tipo de veículo => campo do registro 60 (leiaute 2026). */
    public const VEHICLES = [
        'van' => ['campo' => 28, 'rotulo' => 'Van ou Kombi'],
        'microonibus' => ['campo' => 25, 'rotulo' => 'Micro-ônibus'],
        'onibus' => ['campo' => 26, 'rotulo' => 'Ônibus'],
        'bicicleta' => ['campo' => 24, 'rotulo' => 'Bicicleta'],
        'tracao_animal' => ['campo' => 27, 'rotulo' => 'Tração animal'],
        'rodoviario_outro' => ['campo' => 29, 'rotulo' => 'Outro veículo rodoviário'],
        'barco_ate_5' => ['campo' => 30, 'rotulo' => 'Barco para até 5 alunos'],
        'barco_5_a_15' => ['campo' => 31, 'rotulo' => 'Barco para 5 a 15 alunos'],
        'barco_15_a_35' => ['campo' => 32, 'rotulo' => 'Barco para 15 a 35 alunos'],
        'barco_mais_35' => ['campo' => 33, 'rotulo' => 'Barco para mais de 35 alunos'],
    ];

    protected $table = 'transport_route_census';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'school_id', 'route_name', 'public_power', 'vehicles', 'updated_by'];

    protected function casts(): array
    {
        return ['public_power' => 'integer', 'vehicles' => 'array'];
    }
}
