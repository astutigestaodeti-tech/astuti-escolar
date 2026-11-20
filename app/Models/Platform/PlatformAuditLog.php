<?php

namespace App\Models\Platform;

use App\Audit\ImmutableAuditBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PlatformAuditLog extends Model
{
    protected $connection = 'platform';

    public $incrementing = false;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'before' => 'array', 'after' => 'array'];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Registros de auditoria são imutáveis.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Registros de auditoria não podem ser excluídos.');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('Registros de auditoria não podem ser excluídos fisicamente.');
    }

    public function newEloquentBuilder($query): ImmutableAuditBuilder
    {
        return new ImmutableAuditBuilder($query);
    }
}
