<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

class SchoolRecordBuilder extends Builder
{
    private function denyBulkWrite(): never
    {
        throw new LogicException('Gravações escolares em lote ignoram a auditoria. Use operações individuais nos modelos.');
    }

    public function update(array $values)
    {
        if ($this->getModel()->allowsInternalAuditedUpdate()) {
            return parent::update($values);
        }

        $this->denyBulkWrite();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->denyBulkWrite();
    }

    public function insert(array $values)
    {
        if ($this->getModel()->allowsInternalAuditedUpdate()) {
            return $this->toBase()->insert($values);
        }

        $this->denyBulkWrite();
    }

    public function insertOrIgnore(array $values)
    {
        $this->denyBulkWrite();
    }

    public function insertOrIgnoreReturning(array $values, $returning = ['*'])
    {
        $this->denyBulkWrite();
    }

    public function replace(array $values)
    {
        $this->denyBulkWrite();
    }

    public function insertGetId(array $values, $sequence = null)
    {
        if ($this->getModel()->allowsInternalAuditedUpdate()) {
            return $this->toBase()->insertGetId($values, $sequence);
        }

        $this->denyBulkWrite();
    }

    public function insertUsing(array $columns, $query)
    {
        $this->denyBulkWrite();
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        $this->denyBulkWrite();
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        $this->denyBulkWrite();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->denyBulkWrite();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->denyBulkWrite();
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->denyBulkWrite();
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->denyBulkWrite();
    }

    public function touch($column = null)
    {
        $this->denyBulkWrite();
    }

    public function restore()
    {
        $this->denyBulkWrite();
    }

    public function delete()
    {
        throw new LogicException('Exclusões escolares em lote sem autoria e motivo são proibidas.');
    }

    public function forceDelete()
    {
        throw new LogicException('Exclusão física de registros escolares é proibida.');
    }
}
