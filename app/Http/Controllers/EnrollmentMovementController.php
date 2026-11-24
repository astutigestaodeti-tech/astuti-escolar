<?php

namespace App\Http\Controllers;

use App\Models\Platform\Municipality;
use App\Models\Tenant\School;
use App\Models\Tenant\Student;
use App\Models\Tenant\StudentEnrollment;
use App\Security\TwoFactorSession;
use App\Services\EnrollmentMovementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EnrollmentMovementController extends Controller
{
    public function __construct(private readonly EnrollmentMovementService $movement) {}

    public function transferForm(Request $request, string $municipio, string $escola, string $aluno, string $matricula): View|RedirectResponse
    {
        return $this->form($request, $escola, $aluno, $matricula, 'transferir');
    }

    public function endForm(Request $request, string $municipio, string $escola, string $aluno, string $matricula): View|RedirectResponse
    {
        return $this->form($request, $escola, $aluno, $matricula, 'encerrar');
    }

    public function deleteForm(Request $request, string $municipio, string $escola, string $aluno, string $matricula): View|RedirectResponse
    {
        return $this->form($request, $escola, $aluno, $matricula, 'excluir-cadastro');
    }

    public function transfer(Request $request, string $municipio, string $escola, string $aluno, string $matricula): RedirectResponse
    {
        [$municipality, $school, , $enrollment] = $this->authorizeEnrollment($request, $escola, $aluno, $matricula, false);
        $data = $request->validate([
            'versao' => ['required', 'string', 'max:40'],
            'escola_destino_id' => ['required', 'uuid'],
            'data' => ['required', 'date_format:Y-m-d'],
            'confirmar' => ['required', 'accepted'],
        ]);

        $this->movement->transfer($enrollment, $data['versao'], $data['escola_destino_id'], $data['data']);

        return redirect()->route('school.enrollments.index', [
            'municipio' => $municipality->slug, 'escola' => $school->id,
        ])->with('status', 'Transferência concluída. A matrícula anterior foi encerrada e a matrícula de destino foi criada.')
            ->with('transfer_document', ['student' => $aluno, 'enrollment' => $matricula]);
    }

    public function end(Request $request, string $municipio, string $escola, string $aluno, string $matricula): RedirectResponse
    {
        [$municipality, $school, , $enrollment] = $this->authorizeEnrollment($request, $escola, $aluno, $matricula, false);
        $data = $request->validate([
            'versao' => ['required', 'string', 'max:40'],
            'situacao' => ['required', Rule::in(['left_network', 'abandoned', 'deceased'])],
            'data' => ['required', 'date_format:Y-m-d'],
            'destino' => ['nullable', 'string', 'max:180'],
            'confirmar' => ['required', 'accepted'],
        ]);

        $this->movement->end($enrollment, $data['versao'], $data['situacao'], $data['data'], isset($data['destino']) ? trim($data['destino']) : null);

        return redirect()->route('school.enrollments.index', [
            'municipio' => $municipality->slug, 'escola' => $school->id,
        ])->with('status', 'Matrícula encerrada. O histórico do aluno foi preservado.')
            ->with('transfer_document', $data['situacao'] === 'left_network' ? ['student' => $aluno, 'enrollment' => $matricula] : null);
    }

    public function deleteRegistration(Request $request, string $municipio, string $escola, string $aluno, string $matricula): RedirectResponse
    {
        [$municipality, $school, , $enrollment] = $this->authorizeEnrollment($request, $escola, $aluno, $matricula, true);
        $data = $request->validate([
            'versao' => ['required', 'string', 'max:40'],
            'motivo' => ['required', 'string', 'min:15', 'max:1000'],
            'confirmar' => ['required', 'accepted'],
        ]);

        $this->movement->deleteRegistration($enrollment, $data['versao'], trim($data['motivo']), $request->user()->id);

        return redirect()->route('school.students.index', [
            'municipio' => $municipality->slug, 'escola' => $school->id,
        ])->with('status', 'Cadastro arquivado com justificativa e autoria registradas na auditoria.');
    }

    private function form(Request $request, string $escola, string $aluno, string $matricula, string $action): View|RedirectResponse
    {
        [$municipality, $school, $student, $enrollment] = $this->authorizeEnrollment($request, $escola, $aluno, $matricula, $action === 'excluir-cadastro');
        abort_unless($enrollment->status === 'active', 404);

        if (! app(TwoFactorSession::class)->isVerified($request, $request->user()->fresh())) {
            return redirect()->guest(route('two-factor.challenge'));
        }

        $destinations = $action === 'transferir'
            ? School::query()->where('status', 'active')->where('id', '!=', $school->id)->orderBy('name')->get()
            : collect();

        return view('enrollments.confirm-movement', compact('municipality', 'school', 'student', 'enrollment', 'action', 'destinations'));
    }

    /** @return array{0: Municipality, 1: School, 2: Student, 3: StudentEnrollment} */
    private function authorizeEnrollment(Request $request, string $escola, string $aluno, string $matricula, bool $deletion): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($escola);
        Gate::authorize($deletion ? 'deleteEnrollment' : 'manageStudents', [$school, $municipality]);

        // Checar escola e aluno no mesmo filtro evita revelar fichas de outras unidades.
        $enrollment = StudentEnrollment::query()->selectRaw('student_enrollments.*, xmin::text AS row_token')
            ->where('school_id', $school->id)->where('student_id', $aluno)->findOrFail($matricula);
        $student = Student::query()->findOrFail($aluno);

        return [$municipality, $school, $student, $enrollment];
    }
}
