<?php

namespace App\Import\Students;

use App\Audit\AuditTrail;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentGuardian;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cadastra e vincula o responsável que veio na planilha, dentro da
 * transação da importação. A mesma pessoa nunca vira dois cadastros:
 * pelo CPF, ou, sem CPF, pelo mesmo nome e telefone (irmãos na lista).
 */
final class ImportedGuardianLinker
{
    public const RELATIONSHIPS = ['mother', 'father', 'other'];

    public int $linked = 0;

    /** @var array<string, string> nome normalizado|telefone => id do responsável neste lote */
    private array $batch = [];

    public static function normalizedName(string $name): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name)));
    }

    /** @param array{name: string, relationship: string, phone: ?string, cpf: ?string} $data */
    public function link(Student $student, array $data): void
    {
        $guardian = null;

        if ($data['cpf'] !== null) {
            $guardian = Guardian::query()->where('cpf', $data['cpf'])->lockForUpdate()->first();
            if ($guardian !== null && self::normalizedName($guardian->full_name) !== self::normalizedName($data['name'])) {
                throw ValidationException::withMessages(['lote' => 'O CPF de um responsável foi cadastrado com outro nome depois da prévia. Refaça a conferência; nenhum registro foi gravado.']);
            }
        }

        $key = $data['phone'] === null ? null : self::normalizedName($data['name']).'|'.$data['phone'];
        if ($guardian === null && $key !== null) {
            $guardian = isset($this->batch[$key])
                ? Guardian::query()->find($this->batch[$key])
                : Guardian::query()->where('phone', $data['phone'])->lockForUpdate()->get()
                    ->first(fn (Guardian $candidate): bool => self::normalizedName($candidate->full_name) === self::normalizedName($data['name'])
                        && ($candidate->cpf === null || $data['cpf'] === null || $candidate->cpf === $data['cpf']));
        }

        if ($guardian === null) {
            if ($data['phone'] === null) {
                return;
            }

            $guardian = Guardian::query()->create([
                'id' => (string) Str::uuid(),
                'full_name' => $data['name'],
                'cpf' => $data['cpf'],
                'phone' => $data['phone'],
                'email' => null,
            ]);
            app(AuditTrail::class)->record('tenant', 'created', Guardian::class, $guardian->id, null, $guardian->only(['id', 'full_name', 'cpf', 'phone', 'email']));
        }

        if ($key !== null) {
            $this->batch[$key] = $guardian->id;
        }

        if (StudentGuardian::query()->where('student_id', $student->id)->where('guardian_id', $guardian->id)->exists()) {
            return;
        }

        // O primeiro responsável do aluno vira o contato principal; os demais, não.
        $link = StudentGuardian::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'relationship' => $data['relationship'],
            'is_legal_guardian' => false,
            'is_primary_contact' => ! StudentGuardian::query()->where('student_id', $student->id)->where('is_primary_contact', true)->exists(),
        ]);
        app(AuditTrail::class)->record('tenant', 'created', StudentGuardian::class, $link->id, null, $link->only(['id', 'student_id', 'guardian_id', 'relationship', 'is_legal_guardian', 'is_primary_contact']));
        $this->linked++;
    }
}
