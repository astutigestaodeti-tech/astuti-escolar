<?php

namespace App\Http\Controllers;

use App\Audit\AuditTrail;
use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentGuardian;
use App\Models\Tenant\StudentHealthAlert;
use App\Models\Tenant\StudentPickupAuthorization;
use App\Models\Tenant\StudentSpecialEducation;
use App\Security\TwoFactorSession;
use App\Students\StudentSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentRegistrationPrintController extends Controller
{
    public const GROUPS = [
        'foto' => 'Foto do aluno',
        'identificacao' => 'Identificação complementar',
        'endereco' => 'Endereço residencial',
        'responsaveis' => 'Pais e responsáveis',
        'emergencia' => 'Contatos de emergência (telefones cadastrados)',
        'retirada' => 'Pessoas autorizadas para retirada',
        'matricula' => 'Matrículas ativas nesta escola',
        'transporte' => 'Transporte escolar',
        'saude' => 'Alertas graves de saúde e cuidados essenciais',
        'educacao_especial' => 'Educação especial (categorias e situação do AEE)',
    ];

    public const SENSITIVE = ['saude', 'educacao_especial'];

    public const PURPOSES = [
        'seguranca_escolar' => 'Segurança e cuidados na rotina escolar',
        'planejamento_apoio' => 'Planejamento do apoio educacional',
    ];

    public function configure(Request $request, string $municipio, string $escola, string $aluno): Response|RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizedRecord($request, $escola, $aluno);
        $twoFactor = app(TwoFactorSession::class);
        if (! $twoFactor->isRecentlyVerified($request, $request->user())) {
            $twoFactor->rememberDestination($request, 'Confirme o segundo fator para gerar a ficha cadastral.');

            return redirect()->route('two-factor.challenge');
        }

        $canIncludeSensitive = Gate::allows('manageHealth', [$school, $municipality]);

        return response()->view('students.print-configure', compact('municipality', 'school', 'student', 'canIncludeSensitive'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, string $municipio, string $escola, string $aluno): Response|RedirectResponse
    {
        [$municipality, $school, $student] = $this->authorizedRecord($request, $escola, $aluno);
        $twoFactor = app(TwoFactorSession::class);
        abort_unless($twoFactor->isVerified($request, $request->user()), 403);
        if (! $twoFactor->isRecentlyVerified($request, $request->user())) {
            abort_if($request->expectsJson(), 403, 'Confirme o segundo fator novamente.');

            // Não guardar o POST da impressão na sessão, nem reproduzir a seleção sensível.
            return redirect()->route('school.students.print.configure', ['municipio' => $municipio, 'escola' => $escola, 'aluno' => $aluno])
                ->with('print_notice', 'Confirme o segundo fator novamente e refaça a seleção.');
        }

        $validator = Validator::make($request->only(['grupos', 'finalidade']), [
            'grupos' => ['required', 'array', 'list', 'min:1', 'max:'.count(self::GROUPS)],
            'grupos.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::GROUPS))],
            'finalidade' => ['nullable', 'string', Rule::in(array_keys(self::PURPOSES))],
        ]);
        if ($validator->fails()) {
            return $this->invalidSelection($request, $municipality, $school, $student, 'Escolha pelo menos um grupo de informações válido e revise a finalidade.');
        }
        $validated = $validator->validated();
        // Ordem institucional, não a ordem recebida do navegador.
        $groups = array_values(array_intersect(array_keys(self::GROUPS), $validated['grupos']));
        $sensitive = array_intersect($groups, self::SENSITIVE) !== [];
        $purpose = null;
        if ($sensitive) {
            Gate::authorize('manageHealth', [$school, $municipality]);
            $purpose = $validated['finalidade'] ?? null;
            if ($purpose === null) {
                return $this->invalidSelection($request, $municipality, $school, $student, 'Selecione a finalidade para incluir informações sensíveis.');
            }
        }

        $guardians = collect();
        $pickups = collect();
        $enrollments = collect();
        $alerts = collect();
        $specialEducation = null;

        if (array_intersect($groups, ['responsaveis', 'emergencia']) !== []) {
            $guardians = StudentGuardian::query()->where('student_id', $student->id)
                ->with('guardian')->orderByDesc('is_primary_contact')->orderBy('created_at')->get();
        }
        if (in_array('retirada', $groups, true)) {
            $pickups = StudentPickupAuthorization::query()->where('student_id', $student->id)
                ->orderBy('full_name')->get()->reject(fn (StudentPickupAuthorization $entry): bool => $entry->isExpired());
        }
        if (array_intersect($groups, ['matricula', 'transporte']) !== []) {
            $enrollments = $student->enrollments()->where('school_id', $school->id)->where('status', 'active')
                ->with(['schoolYear', 'schoolClass'])->get()
                ->sortByDesc(fn ($entry): int => (int) ($entry->schoolYear?->year ?? 0));
        }
        if (in_array('saude', $groups, true)) {
            $alerts = StudentHealthAlert::query()->where('student_id', $student->id)->where('severity', 'critical')
                ->orderBy('created_at')->get(['kind', 'alert_text', 'care_instructions']);
        }
        if (in_array('educacao_especial', $groups, true)) {
            $specialEducation = StudentSpecialEducation::query()->find($student->id);
        }

        $generatedAt = now();
        $issuer = $request->user()->name;
        // Somente chaves de seleção validadas, jamais valores da ficha.
        app(AuditTrail::class)->record('tenant', 'student.registration_print.generated', Student::class, $student->id, null, [
            'printed_groups' => implode(',', $groups),
            'print_purpose' => $purpose,
        ]);

        return response()->view('students.print-preview', compact('municipality', 'school', 'student', 'groups', 'purpose', 'guardians', 'pickups', 'enrollments', 'alerts', 'specialEducation', 'generatedAt', 'issuer'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function invalidSelection(Request $request, Municipality $municipality, School $school, Student $student, string $selectionError): Response
    {
        abort_if($request->expectsJson(), 422, $selectionError);
        $canIncludeSensitive = Gate::allows('manageHealth', [$school, $municipality]);
        $allowed = $canIncludeSensitive ? array_keys(self::GROUPS) : array_diff(array_keys(self::GROUPS), self::SENSITIVE);
        $submitted = $request->input('grupos');
        $selectedGroups = is_array($submitted) ? array_values(array_filter($allowed, fn (string $key): bool => in_array($key, $submitted, true))) : [];
        $submittedPurpose = $request->input('finalidade');
        $selectedPurpose = $canIncludeSensitive && is_string($submittedPurpose) && array_key_exists($submittedPurpose, self::PURPOSES) ? $submittedPurpose : null;

        // Resposta direta: não guardar seleção ou finalidade na sessão.
        return response()->view('students.print-configure', compact('municipality', 'school', 'student', 'canIncludeSensitive', 'selectionError', 'selectedGroups', 'selectedPurpose'), 422)
            ->header('Cache-Control', 'private, no-store');
    }

    private function authorizedRecord(Request $request, string $schoolId, string $studentId): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        Gate::authorize('manageStudents', [$school, $municipality]);
        $student = StudentSelection::query($school)->findOrFail($studentId);

        return [$municipality, $school, $student];
    }
}
