<?php

namespace App\Http\Controllers;

use App\Academics\LessonAccess;
use App\Academics\LessonDiary;
use App\Models\Tenant\AcademicPeriod;
use App\Models\Tenant\School;
use App\Models\Tenant\SchoolClass;
use App\Security\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LessonRecordController extends Controller
{
    public function store(Request $request, string $municipio, string $escola, string $turma, LessonAccess $access, LessonDiary $diary): RedirectResponse
    {
        [$municipality, $school, $class, $permissions] = $this->context($request, $escola, $turma, $access);
        abort_unless($permissions['write'], 403);

        $validator = Validator::make($request->all(), [
            'data' => ['required', 'date_format:Y-m-d'],
            'componente' => ['nullable', 'uuid'],
            'conteudo' => ['required', 'string', 'max:1000'],
            'atividade' => ['nullable', 'string', 'max:500'],
            'bncc' => ['nullable', 'string', 'max:300'],
            'versao' => ['required', 'integer', 'min:0', 'max:2147483646'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'operation_id' => ['prohibited'],
        ]);
        // Não guardar textos livres do diário na sessão ao retornar erros.
        if ($validator->fails()) {
            return redirect()->route('school.classes.attendance.index', [
                'municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id,
            ])->withErrors($validator, 'lesson');
        }
        $data = $validator->validated();
        $date = Carbon::parse($data['data'])->startOfDay();
        $component = $data['componente'] ?? null;
        $params = ['municipio' => $municipality->slug, 'escola' => $school->id, 'turma' => $class->id,
            'data' => $date->lte(today()) ? $date->toDateString() : today()->toDateString(),
            'componente_aula' => $component ?? 'polivalente'];
        $return = fn () => redirect()->route('school.classes.attendance.index', $params);
        if (! $diary->dateAllowed($class, $date)) {
            return $return()->withErrors(['data' => 'Escolha uma data dentro do ano letivo, até hoje.'], 'lesson');
        }
        $requiresReason = $permissions['director'] || $date->lt(today());
        if ($requiresReason && mb_strlen(trim($data['motivo'] ?? '')) < 10) {
            return $return()->withErrors(['motivo' => 'Informe o motivo, de 10 a 500 caracteres.'], 'lesson');
        }
        if ($requiresReason && ! app(TwoFactorSession::class)->isRecentlyVerified($request, $request->user())) {
            abort(403, 'Confirme novamente o segundo fator antes de registrar uma correção.');
        }
        $data['motivo'] = $requiresReason ? trim($data['motivo']) : null;
        $codes = preg_split('/[\s,;]+/u', trim($data['bncc'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if ($codes === false || count($codes) > 10 || collect($codes)->contains(fn ($code) => preg_match(LessonDiary::BNCC_FORMAT, $code) !== 1)) {
            return $return()->withErrors(['bncc' => 'Informe até 10 códigos BNCC no formato oficial, separados por espaço, vírgula ou ponto e vírgula.'], 'lesson');
        }
        $data['bncc_codes'] = array_values(array_unique($codes));

        try {
            $diary->save($class, $date, $component, $data, $request->user()->id);
        } catch (ValidationException $exception) {
            return $return()->withErrors($exception->errors(), 'lesson');
        }

        return $return()->with('lesson_status', 'Conteúdo da aula salvo.');
    }

    public function report(Request $request, string $municipio, string $escola, string $turma, LessonAccess $access, LessonDiary $diary): Response
    {
        [$municipality, $school, $class] = $this->context($request, $escola, $turma, $access);
        $input = $request->validate(['periodo' => ['nullable', 'uuid']]);
        $periods = AcademicPeriod::query()->where('school_year_id', $class->school_year_id)->orderBy('number')->get();
        $period = isset($input['periodo']) ? $periods->firstWhere('id', $input['periodo'])
            : ($periods->first(fn ($p) => today()->betweenIncluded($p->starts_on, $p->ends_on)) ?? $periods->first());
        abort_if(isset($input['periodo']) && $period === null, 404);
        $records = $period === null ? collect() : $diary->report($class, $period);

        return response()->view('classes.lesson-report', compact('municipality', 'school', 'class', 'periods', 'period', 'records'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function context(Request $request, string $schoolId, string $classId, LessonAccess $access): array
    {
        $municipality = $request->attributes->get('astuti.municipality');
        $school = School::query()->where('status', 'active')->findOrFail($schoolId);
        $class = SchoolClass::query()->where('school_id', $school->id)->where('status', 'active')->with('schoolYear')->findOrFail($classId);
        $permissions = $access->forClass($request->user(), $municipality, $school, $class);
        abort_unless($permissions['view'], 403);

        return [$municipality, $school, $class, $permissions];
    }
}
