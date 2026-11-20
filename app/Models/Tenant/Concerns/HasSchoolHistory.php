<?php

namespace App\Models\Tenant\Concerns;

use App\Audit\AuditTrail;
use App\Models\Tenant\SchoolRecordBuilder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

trait HasSchoolHistory
{
    use SoftDeletes { restore as protected restoreSoftDeleted; }

    private ?array $auditRestoreBefore = null;

    private bool $deletionAuthorized = false;

    protected static function bootHasSchoolHistory(): void
    {
        static::created(function (self $model): void {
            $model->audit('created', null, $model->getAttributes());
        });

        static::updated(function (self $model): void {
            if ($model->auditRestoreBefore !== null) {
                return;
            }

            $changes = $model->getChanges();
            $before = [];
            $after = [];
            foreach ($changes as $attribute => $value) {
                $before[$attribute] = $model->getRawOriginal($attribute);
                $after[$attribute] = $value;
            }

            if ($after !== []) {
                $model->audit('updated', $before, $after);
            }
        });

        static::deleted(function (self $model): void {
            $model->audit('deleted', ['deleted_at' => null], [
                'deleted_at' => $model->getRawOriginal('deleted_at') ?? $model->getAttribute('deleted_at'),
                'deleted_by' => $model->getAttribute('deleted_by'),
                'deletion_reason' => $model->getAttribute('deletion_reason'),
            ]);
        });

        static::restoring(function (self $model): void {
            $model->auditRestoreBefore = ['deleted_at' => $model->getRawOriginal('deleted_at')];
        });

        static::restored(function (self $model): void {
            try {
                $model->audit('restored', $model->auditRestoreBefore, ['deleted_at' => null]);
            } finally {
                $model->auditRestoreBefore = null;
            }
        });
    }

    /** Exclusão de registro escolar exige justificação humana explícita. */
    public function deleteWithReason(string $reason, ?string $actorId = null): bool
    {
        $actorId ??= Auth::id();

        if (! is_string($actorId) || ! Str::isUuid($actorId) || trim($reason) === '') {
            throw new LogicException('Informe a autoria e o motivo da exclusão lógica.');
        }

        if (! $this->exists || $this->trashed()) {
            throw new LogicException('O registro não está disponível para exclusão.');
        }

        if ($this->isDirty()) {
            throw new LogicException('Salve ou descarte as alterações pendentes antes de excluir.');
        }

        return $this->getConnection()->transaction(function () use ($actorId, $reason): bool {
            $this->setAttribute('deleted_by', $actorId);
            $this->setAttribute('deletion_reason', trim($reason));
            $this->saveQuietly(); // Apenas metadados da exclusão, sem outros campos pendentes.

            $this->deletionAuthorized = true;
            try {
                return $this->delete() === true;
            } finally {
                $this->deletionAuthorized = false;
            }
        });
    }

    public function delete(): ?bool
    {
        if (! $this->deletionAuthorized || ! $this->getAttribute('deleted_by') || ! $this->getAttribute('deletion_reason')) {
            throw new LogicException('Exclusão escolar exige autoria e motivo. Use deleteWithReason().');
        }

        return parent::delete();
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('Exclusão física de registros escolares é proibida.');
    }

    public function restore(): bool
    {
        return $this->getConnection()->transaction(fn (): bool => $this->restoreSoftDeleted());
    }

    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(fn (): bool => parent::save($options));
    }

    public function newEloquentBuilder($query): SchoolRecordBuilder
    {
        return new SchoolRecordBuilder($query);
    }

    private function audit(string $action, ?array $before, ?array $after): void
    {
        app(AuditTrail::class)->record(
            connection: 'tenant',
            action: $action,
            auditableType: static::class,
            auditableId: (string) $this->getKey(),
            before: $before,
            after: $after,
            userId: $action === 'deleted' ? $this->getAttribute('deleted_by') : null,
        );
    }
}
