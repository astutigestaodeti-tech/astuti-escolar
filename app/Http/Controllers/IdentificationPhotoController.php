<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Tenant\Guardian;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Photos\IdentificationPhoto;
use App\Support\UploadLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Foto de identificação do aluno, do responsável e da pessoa autorizada a
 * buscar. Consulta: quem vê a escola. Envio e remoção: quem gere alunos,
 * com segundo fator. O arquivo nunca tem endereço público.
 */
class IdentificationPhotoController extends Controller
{
    public const TYPES = ['aluno', 'responsavel', 'autorizado'];

    private const FOLDERS = ['aluno' => 'alunos', 'responsavel' => 'responsaveis', 'autorizado' => 'autorizados'];

    public function show(Request $request, string $municipio, string $escola, string $tipo, string $id): Response
    {
        [, $school, $municipality] = $this->school($request, $escola);
        Gate::authorize('view', [$school, $municipality]);

        $path = $this->entity($tipo, $id, $school)->photo_path;
        abort_if($path === null || ! Storage::disk(IdentificationPhoto::DISK)->exists($path), 404);

        return response(Storage::disk(IdentificationPhoto::DISK)->get($path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="foto.jpg"',
        ]);
    }

    public function update(Request $request, string $municipio, string $escola, string $tipo, string $id, IdentificationPhoto $photos): RedirectResponse
    {
        [, $school, $municipality] = $this->school($request, $escola);
        Gate::authorize('manageStudents', [$school, $municipality]);
        $entity = $this->entity($tipo, $id, $school);

        $this->assertUploadArrived($request);

        $request->validate(
            ['foto' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp']],
            [
                'foto.required' => 'Escolha uma foto.',
                'foto.max' => 'A foto tem mais de 8 MB. Escolha uma foto menor.',
                'foto.mimes' => 'Envie uma foto JPG, PNG ou WebP.',
                'foto.file' => 'Escolha uma foto.',
            ],
        );

        $old = $entity->photo_path;
        $path = $photos->store($request->file('foto'), $municipality->slug.'/'.self::FOLDERS[$tipo]);
        $this->save($entity, $path, 'photo.updated');
        $photos->delete($old);

        return back()->with('status', $old === null ? 'Foto adicionada.' : 'Foto atualizada.');
    }

    public function destroy(Request $request, string $municipio, string $escola, string $tipo, string $id, IdentificationPhoto $photos): RedirectResponse
    {
        [, $school, $municipality] = $this->school($request, $escola);
        Gate::authorize('manageStudents', [$school, $municipality]);
        $entity = $this->entity($tipo, $id, $school);

        $old = $entity->photo_path;
        if ($old !== null) {
            $this->save($entity, null, 'photo.removed');
            $photos->delete($old);
        }

        return back()->with('status', 'Foto removida.');
    }

    /**
     * O PHP descarta o arquivo acima do limite do servidor antes da validação.
     * Explica o motivo em vez da mensagem genérica de envio.
     */
    private function assertUploadArrived(Request $request): void
    {
        $file = $request->files->get('foto');
        if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_OK) {
            return;
        }

        $message = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'A foto é maior que o limite de envio deste servidor ('.UploadLimit::label().'). Escolha uma foto menor ou recorte a imagem.'
            : 'A foto não chegou completa. Tente enviar de novo.';

        throw ValidationException::withMessages(['foto' => $message]);
    }

    /** @return array{0: mixed, 1: School, 2: mixed} */
    private function school(Request $request, string $escola): array
    {
        $municipality = $request->attributes->get('astuti.municipality');

        return [null, School::query()->where('status', 'active')->findOrFail($escola), $municipality];
    }

    /** Só pessoas ligadas a aluno com matrícula ativa nesta escola. */
    private function entity(string $tipo, string $id, School $school): Model
    {
        $activeHere = fn (Builder $enrollments) => $enrollments->where('school_id', $school->id)->where('status', 'active');

        return match ($tipo) {
            'aluno' => Student::query()->whereHas('enrollments', $activeHere)->findOrFail($id),
            'responsavel' => Guardian::query()->whereIn('id', StudentGuardian::query()
                ->whereHas('student', fn (Builder $student) => $student->whereHas('enrollments', $activeHere))
                ->select('guardian_id'))->findOrFail($id),
            'autorizado' => StudentPickupAuthorization::query()
                ->whereHas('student', fn (Builder $student) => $student->whereHas('enrollments', $activeHere))->findOrFail($id),
            default => abort(404),
        };
    }

    private function save(Model $entity, ?string $path, string $action): void
    {
        $now = $path === null ? null : now();
        $before = ['photo_updated_at' => $entity->photo_updated_at?->toIso8601String()];
        $entity->forceFill(['photo_path' => $path, 'photo_updated_at' => $now])->save();

        // O aluno já audita sozinho ao salvar; responsável e autorizado, aqui.
        if (! $entity instanceof Student) {
            app(AuditTrail::class)->record('tenant', $action, $entity::class, $entity->getKey(), $before, ['photo_updated_at' => $now?->toIso8601String()]);
        }
    }
}
