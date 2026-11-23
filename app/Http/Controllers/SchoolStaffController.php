<?php

namespace App\Http\Controllers;

use App\Models\Tenant\ClassTeacherAssignment;
use App\Models\Tenant\School;
use App\Models\Tenant\StaffMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolStaffController extends Controller
{
    public const ROLES = [
        'teacher' => 'Professor(a)',
        'assistant' => 'Auxiliar',
        'coordinator' => 'Coordenação',
        'director' => 'Direção',
        'secretary' => 'Secretaria',
        'other' => 'Outro',
    ];

    public function index(Request $request, string $municipio, string $escola): View
    {
        [$municipality, $school] = $this->authorizedSchool($request, $escola);
        $search = trim((string) $request->query('busca', ''));
        $search = mb_substr($search, 0, 120);
        $query = StaffMember::query()->where('school_id', $school->id);

        if ($search !== '') {
            $term = '%'.addcslashes($search, '\\%_').'%';
            $query->whereRaw('public.f_unaccent(lower(staff_members.full_name)) LIKE public.f_unaccent(lower(?))', [$term]);
        }

        $staff = $query->orderBy('full_name')->get();

        return view('staff.index', [
            'municipality' => $municipality, 'school' => $school, 'staff' => $staff,
            'search' => $search, 'roles' => self::ROLES,
            'canManage' => Gate::allows('manageStudents', [$school, $municipality]),
        ]);
    }

    public function create(Request $request, string $municipio, string $escola): View
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);

        return view('staff.form', [
            'municipality' => $municipality, 'school' => $school,
            'member' => null, 'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request, string $municipio, string $escola): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $data = $this->validatedData($request);

        DB::connection('tenant')->transaction(function () use ($school, $data): void {
            StaffMember::query()->create([
                'id' => (string) Str::uuid(), 'school_id' => $school->id, ...$data,
            ]);
        });

        return redirect()->route('school.staff.index', $this->params($municipality->slug, $school->id))
            ->with('status', 'Servidor cadastrado com sucesso.');
    }

    public function edit(Request $request, string $municipio, string $escola, string $servidor): View
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $member = $this->memberInSchool($school, $servidor);

        return view('staff.form', [
            'municipality' => $municipality, 'school' => $school,
            'member' => $member, 'roles' => self::ROLES,
        ]);
    }

    public function update(Request $request, string $municipio, string $escola, string $servidor): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $data = $this->validatedData($request);

        DB::connection('tenant')->transaction(function () use ($school, $servidor, $data): void {
            $member = StaffMember::query()->where('school_id', $school->id)->lockForUpdate()->findOrFail($servidor);
            if (($data['role'] !== 'teacher' || $data['status'] !== 'active')
                && ClassTeacherAssignment::query()->where('staff_member_id', $member->id)->whereNull('ends_on')->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'Encerre as atribuições em aberto antes de mudar o cargo ou desativar este servidor.',
                ]);
            }
            $member->fill($data);
            if ($member->isDirty()) {
                $member->save();
            }
        });

        return redirect()->route('school.staff.index', $this->params($municipality->slug, $school->id))
            ->with('status', 'Cadastro do servidor atualizado.');
    }

    public function removeForm(Request $request, string $municipio, string $escola, string $servidor): View
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $member = $this->memberInSchool($school, $servidor);

        return view('staff.remove', compact('municipality', 'school', 'member'));
    }

    public function destroy(Request $request, string $municipio, string $escola, string $servidor): RedirectResponse
    {
        [$municipality, $school] = $this->managedSchool($request, $escola);
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivo.required' => 'Informe o motivo do arquivamento.',
            'motivo.min' => 'O motivo deve ter pelo menos 10 caracteres.',
            'motivo.max' => 'O motivo deve ter no máximo 500 caracteres.',
        ]);

        DB::connection('tenant')->transaction(function () use ($school, $servidor, $data, $request): void {
            $member = StaffMember::query()->where('school_id', $school->id)->lockForUpdate()->findOrFail($servidor);
            if (ClassTeacherAssignment::query()->where('staff_member_id', $member->id)->whereNull('ends_on')->exists()) {
                throw ValidationException::withMessages([
                    'motivo' => 'Encerre primeiro as atribuições em aberto deste servidor.',
                ]);
            }
            $member->deleteWithReason(trim($data['motivo']), (string) $request->user()->id);
        });

        return redirect()->route('school.staff.index', $this->params($municipality->slug, $school->id))
            ->with('status', 'Cadastro do servidor arquivado. O histórico das turmas foi preservado.');
    }

    private function validatedData(Request $request): array
    {
        foreach (['full_name', 'registration_number', 'institutional_email'] as $key) {
            $value = $request->input($key);
            if (is_string($value)) {
                $request->merge([$key => preg_replace('/\s+/u', ' ', trim($value))]);
            }
        }
        $phone = $request->input('phone');
        if (is_string($phone)) {
            $request->merge(['phone' => preg_replace('/\D+/', '', $phone)]);
        }

        return $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'institutional_email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'regex:/\A(?:\d{10}|\d{11})\z/D'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'full_name.required' => 'Informe o nome completo do servidor.',
            'role.required' => 'Escolha a função do servidor.',
            'role.in' => 'Escolha uma função válida.',
            'institutional_email.email' => 'Informe um e-mail institucional válido.',
            'phone.regex' => 'Informe um telefone com DDD (10 ou 11 números).',
            'status.required' => 'Escolha a situação do servidor.',
            'status.in' => 'Escolha uma situação válida.',
        ], [
            'full_name' => 'Nome completo',
            'registration_number' => 'Matrícula funcional',
            'role' => 'Função',
            'institutional_email' => 'E-mail institucional',
            'phone' => 'Telefone',
            'status' => 'Situação',
        ]);
    }

    private function memberInSchool(School $school, string $id): StaffMember
    {
        return StaffMember::query()->where('school_id', $school->id)->findOrFail($id);
    }

    private function authorizedSchool(Request $request, string $id): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($id);
        Gate::authorize('view', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function managedSchool(Request $request, string $id): array
    {
        [$municipality, $school] = $this->authorizedSchool($request, $id);
        Gate::authorize('manageStudents', [$school, $municipality]);

        return [$municipality, $school];
    }

    private function params(string $slug, string $schoolId): array
    {
        return ['municipio' => $slug, 'escola' => $schoolId];
    }
}
