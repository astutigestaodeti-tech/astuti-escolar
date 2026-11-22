<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    protected $connection = 'platform';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'slug',
        'legal_name',
        'db_key',
        'status',
    ];
}
