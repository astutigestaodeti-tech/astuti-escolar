<?php

namespace App\Models\Tenant\Concerns;

use App\Audit\AuditTrail;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Models\Tenant\SchoolRecordBuilder;
use App\Models\Tenant\SchoolYear;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Events\NullDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

trait HasSchoolHistory
{
    use SoftDeletes { restore as protected restoreSoftDeleted; }

    private ?array $auditRestoreBefore = null;

    private bool $deletionAuthorized = false;

    private bool $auditedModelWriteAuthorized = false;

    private bool $restorationAuthorized = false;

    private ?string $restorationActorId = null;

    private ?string $restorationReason = null;

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
                $model->audit('restored', $model->auditRestoreBefore, [
                    'deleted_at' => null,
                    'restored_by' => $model->restorationActorId,
                    'restoration_reason' => $model->restorationReason,
                ]);
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

        $this->assertEventsEnabled();

        return $this->getConnection()->transaction(function () use ($actorId, $reason): bool {
            $this->assertNoActiveDependents();

            // Só a rotina autorizada pode alterar metadados antes da exclusão.
            // Uma gravação de modelo silenciosa abriria um caminho sem auditoria.
            $this->setAttribute('deleted_by', $actorId);
            $this->setAttribute('deletion_reason', trim($reason));
            $this->getConnection()->table($this->getTable())
                ->where($this->getKeyName(), $this->getKey())
                ->update(['deleted_by' => $actorId, 'deletion_reason' => trim($reason)]);
            $this->syncOriginalAttributes(['deleted_by', 'deletion_reason']);

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
        $this->assertEventsEnabled();

        if (! $this->deletionAuthorized || ! $this->getAttribute('deleted_by') || ! $this->getAttribute('deletion_reason')) {
            throw new LogicException('Exclusão escolar exige autoria e motivo. Use deleteWithReason().');
        }

        $this->auditedModelWriteAuthorized = true;
        try {
            return parent::delete();
        } finally {
            $this->auditedModelWriteAuthorized = false;
        }
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('Exclusão física de registros escolares é proibida.');
    }

    public function restore(): bool
    {
        $this->assertEventsEnabled();

        if (! $this->restorationAuthorized || ! $this->restorationActorId || ! $this->restorationReason) {
            throw new LogicException('Restauração exige autoria e motivo. Use restoreWithReason().');
        }

        return $this->getConnection()->transaction(fn (): bool => $this->restoreSoftDeleted());
    }

    /** Restauração é uma decisão humana auditada, nunca uma operação silenciosa. */
    public function restoreWithReason(string $reason, string $actorId): bool
    {
        if (! Str::isUuid($actorId) || trim($reason) === '') {
            throw new LogicException('Informe a autoria e o motivo da restauração.');
        }

        if (! $this->exists || ! $this->trashed()) {
            throw new LogicException('O registro não está arquivado para restauração.');
        }

        if ($this->isDirty()) {
            throw new LogicException('Descarte as alterações pendentes antes de restaurar.');
        }

        $this->assertEventsEnabled();

        return $this->getConnection()->transaction(function () use ($reason, $actorId): bool {
            $this->assertActiveParents();
            $this->restorationActorId = $actorId;
            $this->restorationReason = trim($reason);
            $this->restorationAuthorized = true;

            try {
                return $this->restore();
            } finally {
                $this->restorationAuthorized = false;
                $this->restorationActorId = null;
                $this->restorationReason = null;
            }
        });
    }

    public function save(array $options = []): bool
    {
        $this->assertEventsEnabled();

        return $this->getConnection()->transaction(function () use ($options): bool {
            $this->auditedModelWriteAuthorized = true;
            try {
                return parent::save($options);
            } finally {
                $this->auditedModelWriteAuthorized = false;
            }
        });
    }

    /** Permite somente UPDATE disparado pelo próprio Model::save/delete auditado. */
    public function allowsInternalAuditedUpdate(): bool
    {
        return $this->auditedModelWriteAuthorized;
    }

    public function newEloquentBuilder($query): SchoolRecordBuilder
    {
        return new SchoolRecordBuilder($query);
    }

    private function assertEventsEnabled(): void
    {
        if (static::getEventDispatcher() instanceof NullDispatcher) {
            throw new LogicException('Registros escolares não podem ser gravados sem eventos de auditoria.');
        }
    }

    /** Impede pais arquivados com filhos ainda ativos, sem exclusões em cascata. */
    private function assertNoActiveDependents(): void
    {
        $dependents = match (true) {
            $this instanceof School =>
                SchoolClass::query()->where('school_id', $this->getKey())->exists()
                || StudentEnrollment::query()->where('school_id', $this->getKey())->exists(),
            $this instanceof SchoolYear =>
                SchoolClass::query()->where('school_year_id', $this->getKey())->exists()
                || StudentEnrollment::query()->where('school_year_id', $this->getKey())->exists(),
            $this instanceof Student =>
                StudentEnrollment::query()->where('student_id', $this->getKey())->exists(),
            default => false,
        };

        if ($dependents) {
            throw new LogicException('Arquive primeiro as turmas ou matrículas vinculadas a este registro.');
        }
    }

    private function assertActiveParents(): void
    {
        $parentsPresent = match (true) {
            $this instanceof SchoolClass =>
                School::query()->whereKey($this->getAttribute('school_id'))->exists()
                && SchoolYear::query()->whereKey($this->getAttribute('school_year_id'))->exists(),
            $this instanceof StudentEnrollment =>
                Student::query()->whereKey($this->getAttribute('student_id'))->exists()
                && School::query()->whereKey($this->getAttribute('school_id'))->exists()
                && SchoolYear::query()->whereKey($this->getAttribute('school_year_id'))->exists(),
            default => true,
        };

        if (! $parentsPresent) {
            throw new LogicException('Restaure primeiro a escola, o aluno ou o ano letivo vinculado.');
        }
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
            userId: match ($action) {
                'deleted' => $this->getAttribute('deleted_by'),
                'restored' => $this->restorationActorId,
                default => null,
            },
        );
    }
}
