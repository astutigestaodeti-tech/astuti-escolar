<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $connection = 'platform';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'municipality_id',
        'role',
        'status',
    ];
}
