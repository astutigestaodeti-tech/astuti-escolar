<?php

use App\Http\Controllers\AcademicConfigController;
use App\Http\Controllers\ActiveSearchController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\CensusReadinessController;
use App\Http\Controllers\ClassCensusController;
use App\Http\Controllers\ClassGradesController;
use App\Http\Controllers\ClassTeacherAssignmentController;
use App\Http\Controllers\ClassYearEndController;
use App\Http\Controllers\ConfirmedStudentImportController;
use App\Http\Controllers\ConfirmedTransportImportController;
use App\Http\Controllers\DemonstrationController;
use App\Http\Controllers\EnrollmentDeclarationController;
use App\Http\Controllers\EnrollmentMovementController;
use App\Http\Controllers\EnrollmentRenewalController;
use App\Http\Controllers\EquityVaarController;
use App\Http\Controllers\FullTimeEducationController;
use App\Http\Controllers\GeoLocationController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdentificationPhotoController;
use App\Http\Controllers\InstitutionProfileController;
use App\Http\Controllers\LiteracyController;
use App\Http\Controllers\MonthlySecretariatReportController;
use App\Http\Controllers\MultiprofessionalController;
use App\Http\Controllers\MultiprofessionalIndicatorsController;
use App\Http\Controllers\NetworkPanelController;
use App\Http\Controllers\PostalCodeLookupController;
use App\Http\Controllers\PublicDocumentVerificationController;
use App\Http\Controllers\SchoolAbsenceAlertsController;
use App\Http\Controllers\SchoolAttendanceController;
use App\Http\Controllers\SchoolCalendarController;
use App\Http\Controllers\SchoolCensusController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolEnrollmentController;
use App\Http\Controllers\SchoolInfrastructureCensusController;
use App\Http\Controllers\SchoolMealsController;
use App\Http\Controllers\SchoolMonthlyAttendanceReportController;
use App\Http\Controllers\SchoolPendenciesController;
use App\Http\Controllers\SchoolStaffController;
use App\Http\Controllers\SchoolStudentController;
use App\Http\Controllers\SchoolTranscriptController;
use App\Http\Controllers\SchoolTransportRoutesController;
use App\Http\Controllers\StaffCensusController;
use App\Http\Controllers\StudentCensusController;
use App\Http\Controllers\StudentExitController;
use App\Http\Controllers\StudentHealthController;
use App\Http\Controllers\StudentPickupAuthorizationController;
use App\Http\Controllers\StudentRecordController;
use App\Http\Controllers\StudentReportCardController;
use App\Http\Controllers\StudentsCensusController;
use App\Http\Controllers\StudentSpecialEducationController;
use App\Http\Controllers\TransportCensusController;
use App\Http\Controllers\WaitlistController;
use App\Http\Middleware\RequireConfirmedTwoFactor;
use App\Http\Middleware\RequireLoginTwoFactor;
use App\Http\Middleware\ResolveMunicipality;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->middleware(RequireLoginTwoFactor::class)->name('home');

Route::get('/saude', fn () => response()->json(['status' => 'ok']));

Route::get('/demonstracao', [DemonstrationController::class, 'index'])->name('demo');

// A consulta pública é restrita ao município configurado nesta VPS e não usa sessão.
Route::get('/verificar', [PublicDocumentVerificationController::class, 'form'])
    ->middleware('throttle:20,1')->name('documents.verify.form');
Route::get('/verificar/{codigo}', PublicDocumentVerificationController::class)
    ->middleware('throttle:20,1')->name('documents.verify');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:astuti-login')->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('/seguranca/segundo-fator')->middleware('auth')->group(function (): void {
    Route::get('/', [TwoFactorController::class, 'show'])->name('two-factor.settings');
    Route::post('/iniciar', [TwoFactorController::class, 'begin'])->middleware('throttle:astuti-second-factor')->name('two-factor.begin');
    Route::post('/confirmar', [TwoFactorController::class, 'confirm'])->middleware('throttle:astuti-second-factor')->name('two-factor.confirm');
    Route::get('/desafio', [TwoFactorController::class, 'challengePage'])->name('two-factor.challenge');
    Route::post('/desafio', [TwoFactorController::class, 'challenge'])->middleware('throttle:astuti-second-factor')->name('two-factor.verify');
    Route::post('/desativar', [TwoFactorController::class, 'disable'])->middleware('throttle:astuti-second-factor')->name('two-factor.disable');
});

Route::prefix('/m/{municipio}')->middleware(['auth', RequireLoginTwoFactor::class, ResolveMunicipality::class])->group(function (): void {
    // Lista de espera: somente gestão municipal e unidades autorizadas. Sem acesso público.
    Route::get('/lista-de-espera', [WaitlistController::class, 'index'])->name('waitlist.index');
    Route::get('/lista-de-espera/novo', [WaitlistController::class, 'create'])->middleware(RequireConfirmedTwoFactor::class)->name('waitlist.create');
    Route::post('/lista-de-espera', [WaitlistController::class, 'store'])
        ->middleware(RequireConfirmedTwoFactor::class)->name('waitlist.store');
    Route::get('/lista-de-espera/cep/{cep}', [WaitlistController::class, 'postalLookup'])
        ->middleware('throttle:20,1')->where('cep', '[0-9-]+')->name('waitlist.postal.lookup');
    Route::get('/lista-de-espera/{pedido}', [WaitlistController::class, 'show'])->whereUuid('pedido')->name('waitlist.show');
    Route::post('/lista-de-espera/{pedido}/situacao', [WaitlistController::class, 'changeStatus'])
        ->whereUuid('pedido')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('waitlist.status');
    Route::get('/lista-de-espera/{pedido}/matricular', [WaitlistController::class, 'enrollForm'])
        ->whereUuid('pedido')->middleware(RequireConfirmedTwoFactor::class)->name('waitlist.enroll.form');
    Route::post('/lista-de-espera/{pedido}/matricular', [WaitlistController::class, 'enroll'])
        ->whereUuid('pedido')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('waitlist.enroll');
    Route::post('/lista-de-espera/{pedido}/arquivar', [WaitlistController::class, 'archive'])
        ->whereUuid('pedido')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('waitlist.archive');
    // Configuração municipal de notas e períodos, exclusiva da Secretaria.
    Route::get('/avaliacoes/componentes', [AcademicConfigController::class, 'components'])->name('academic.components.index');
    Route::post('/avaliacoes/componentes', [AcademicConfigController::class, 'storeComponent'])
        ->middleware(RequireConfirmedTwoFactor::class)->name('academic.components.store');
    Route::post('/avaliacoes/componentes/{componente}/situacao', [AcademicConfigController::class, 'toggleComponent'])
        ->whereUuid('componente')->middleware(RequireConfirmedTwoFactor::class)->name('academic.components.toggle');
    Route::get('/avaliacoes/anos/{ano}/periodos', [AcademicConfigController::class, 'periods'])
        ->whereUuid('ano')->name('academic.periods.index');
    Route::post('/avaliacoes/anos/{ano}/periodos', [AcademicConfigController::class, 'updatePeriods'])
        ->whereUuid('ano')->middleware(RequireConfirmedTwoFactor::class)->name('academic.periods.update');
    Route::post('/avaliacoes/anos/{ano}/periodos/{periodo}/fechar', [AcademicConfigController::class, 'closePeriod'])
        ->whereUuid('ano')->whereUuid('periodo')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('academic.periods.close');
    Route::get('/painel-da-rede', [NetworkPanelController::class, 'index'])->name('network.panel.index');
    Route::get('/relatorio-mensal-secretaria', [MonthlySecretariatReportController::class, 'index'])->name('secretariat.monthly.index');
    Route::get('/equipe-multiprofissional/indicadores', [MultiprofessionalIndicatorsController::class, 'index'])->name('team.indicators');
    Route::get('/equipe-multiprofissional', [MultiprofessionalController::class, 'index'])
        ->middleware(RequireConfirmedTwoFactor::class)->name('team.index');
    Route::get('/equipe-multiprofissional/{pedido}', [MultiprofessionalController::class, 'show'])
        ->whereUuid('pedido')->middleware(RequireConfirmedTwoFactor::class)->name('team.show');
    Route::get('/equipe-multiprofissional/{pedido}/encaminhamentos/{encaminhamento}', [MultiprofessionalController::class, 'document'])
        ->whereUuid('pedido')->whereUuid('encaminhamento')->middleware(RequireConfirmedTwoFactor::class)->name('team.document');
    Route::middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->group(function (): void {
        Route::post('/escolas/{escola}/equipe-multiprofissional/{matricula}', [MultiprofessionalController::class, 'store'])
            ->whereUuid('escola')->whereUuid('matricula')->name('school.team.store');
        Route::post('/escolas/{escola}/busca-ativa/{matricula}/equipe-multiprofissional', [MultiprofessionalController::class, 'fromActiveSearch'])
            ->whereUuid('escola')->whereUuid('matricula')->name('school.team.active-search');
        Route::post('/equipe-multiprofissional/{pedido}/assumir', [MultiprofessionalController::class, 'claim'])->whereUuid('pedido')->name('team.claim');
        Route::post('/equipe-multiprofissional/{pedido}/agendar', [MultiprofessionalController::class, 'schedule'])->whereUuid('pedido')->name('team.schedule');
        Route::post('/equipe-multiprofissional/{pedido}/atendimento', [MultiprofessionalController::class, 'attend'])->whereUuid('pedido')->name('team.attend');
        Route::post('/equipe-multiprofissional/{pedido}/devolutiva', [MultiprofessionalController::class, 'guidance'])->whereUuid('pedido')->name('team.guidance');
        Route::post('/equipe-multiprofissional/{pedido}/encaminhar', [MultiprofessionalController::class, 'refer'])->whereUuid('pedido')->name('team.refer');
        Route::post('/equipe-multiprofissional/{pedido}/encaminhamentos/{encaminhamento}/retorno', [MultiprofessionalController::class, 'recordReturn'])
            ->whereUuid('pedido')->whereUuid('encaminhamento')->name('team.return');
        Route::post('/equipe-multiprofissional/{pedido}/encerrar', [MultiprofessionalController::class, 'close'])->whereUuid('pedido')->name('team.close');
    });
    // Dados da Prefeitura: timbre e rodapé de todos os documentos emitidos.
    Route::get('/dados-da-prefeitura', [InstitutionProfileController::class, 'edit'])->name('institution.edit');
    Route::post('/dados-da-prefeitura', [InstitutionProfileController::class, 'update'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('institution.update');
    // Busca ativa: ficha restrita, alertas de turma e documento verificável.
    Route::get('/alfabetizacao', [LiteracyController::class, 'network'])->name('literacy.network');
    Route::post('/alfabetizacao/politica', [LiteracyController::class, 'settings'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('literacy.settings');
    Route::get('/escolas/{escola}/alfabetizacao', [LiteracyController::class, 'school'])
        ->whereUuid('escola')->name('school.literacy.panel');
    Route::get('/escolas/{escola}/turmas/{turma}/alfabetizacao', [LiteracyController::class, 'classroom'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.literacy.classroom');
    Route::post('/escolas/{escola}/turmas/{turma}/alfabetizacao', [LiteracyController::class, 'save'])
        ->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.literacy.save');

    Route::get('/busca-ativa', [ActiveSearchController::class, 'network'])->name('active-search.network');
    Route::post('/busca-ativa/parametros', [ActiveSearchController::class, 'saveParameters'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('active-search.parameters');
    Route::get('/escolas/{escola}/busca-ativa', [ActiveSearchController::class, 'school'])
        ->whereUuid('escola')->name('school.active-search.index');
    Route::get('/escolas/{escola}/turmas/{turma}/busca-ativa', [ActiveSearchController::class, 'classAlerts'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.active-search.class');
    Route::get('/escolas/{escola}/busca-ativa/{matricula}', [ActiveSearchController::class, 'detail'])
        ->whereUuid('escola')->whereUuid('matricula')->name('school.active-search.detail');
    Route::post('/escolas/{escola}/busca-ativa/{matricula}', [ActiveSearchController::class, 'saveCase'])
        ->whereUuid('escola')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.active-search.save');
    Route::post('/escolas/{escola}/busca-ativa/{matricula}/contatos', [ActiveSearchController::class, 'contact'])
        ->whereUuid('escola')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.active-search.contact');
    Route::post('/escolas/{escola}/busca-ativa/{matricula}/encaminhamento', [ActiveSearchController::class, 'issue'])
        ->whereUuid('escola')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.active-search.issue');
    Route::get('/escolas/{escola}/busca-ativa/{matricula}/documentos/{documento}', [ActiveSearchController::class, 'document'])
        ->whereUuid('escola')->whereUuid('matricula')->whereUuid('documento')->name('school.active-search.document');
    Route::post('/escolas/{escola}/busca-ativa/{matricula}/documentos/{documento}/revogar', [ActiveSearchController::class, 'revoke'])
        ->whereUuid('escola')->whereUuid('matricula')->whereUuid('documento')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.active-search.revoke');
    Route::get('/conferencia-do-censo', [CensusReadinessController::class, 'index'])->name('census.readiness.index');
    Route::post('/conferencia-do-censo/parametros', [CensusReadinessController::class, 'updateParameters'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('census.readiness.parameters');
    // Alimentação escolar: cardápio na rede, registro na escola, PNAE agregado.
    Route::get('/alimentacao/cardapio', [SchoolMealsController::class, 'menu'])->name('meals.menu');
    Route::post('/alimentacao/cardapio', [SchoolMealsController::class, 'saveMenu'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('meals.menu.save');
    Route::post('/alimentacao/cardapio/copiar', [SchoolMealsController::class, 'copyMenu'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('meals.menu.copy');
    Route::get('/alimentacao/relatorio', [SchoolMealsController::class, 'report'])->name('meals.report');
    Route::get('/escolas/{escola}/alimentacao', [SchoolMealsController::class, 'school'])
        ->whereUuid('escola')->name('school.meals.index');
    Route::post('/escolas/{escola}/alimentacao', [SchoolMealsController::class, 'saveServing'])
        ->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.meals.save');
    Route::get('/escolas/{escola}/alimentacao/cardapio', [SchoolMealsController::class, 'schoolMenu'])
        ->whereUuid('escola')->name('school.meals.menu');
    Route::get('/escolas/{escola}/alimentacao/cozinha', [SchoolMealsController::class, 'kitchen'])
        ->whereUuid('escola')->name('school.meals.kitchen');
    Route::get('/escolas/{escola}/alimentacao/restricoes', [SchoolMealsController::class, 'allergies'])
        ->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.meals.allergies');
    Route::get('/escolas/{escola}/alimentacao/relatorio', [SchoolMealsController::class, 'schoolReport'])
        ->whereUuid('escola')->name('school.meals.report');
    // Tempo integral: rede pela gestao, escola propria pela direcao/secretaria.
    Route::get('/tempo-integral', [FullTimeEducationController::class, 'index'])->name('integral.index');
    Route::get('/escolas/{escola}/tempo-integral', [FullTimeEducationController::class, 'school'])
        ->whereUuid('escola')->name('integral.school');
    Route::post('/tempo-integral/metas', [FullTimeEducationController::class, 'saveTarget'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('integral.target');
    Route::post('/tempo-integral/receita', [FullTimeEducationController::class, 'saveRevenue'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('integral.revenue');
    Route::post('/tempo-integral/aplicacoes', [FullTimeEducationController::class, 'storeExpense'])
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('integral.expense.store');
    Route::get('/tempo-integral/aplicacoes/{despesa}/documento', [FullTimeEducationController::class, 'evidence'])
        ->whereUuid('despesa')->name('integral.expense.evidence');
    Route::post('/escolas/{escola}/turmas/{turma}/jornada', [FullTimeEducationController::class, 'updateHours'])
        ->whereUuid('escola')->whereUuid('turma')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('integral.hours.update');
    // Equidade: totais anonimizados para a rede e para a direcao de cada escola.
    Route::get('/equidade-vaar', [EquityVaarController::class, 'index'])->name('equity.vaar.index');
    Route::get('/escolas/{escola}/equidade-vaar', [EquityVaarController::class, 'school'])
        ->whereUuid('escola')->name('equity.vaar.school');
    Route::post('/equidade-vaar/condicionalidades/{condicao}', [EquityVaarController::class, 'updateCondition'])
        ->where('condicao', '[1-5]')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('equity.vaar.condition');
    Route::get('/equidade-vaar/condicionalidades/{condicao}/documento', [EquityVaarController::class, 'evidence'])
        ->where('condicao', '[1-5]')->name('equity.vaar.evidence');
    Route::get('/escolas', [SchoolController::class, 'index'])->name('schools.index');
    Route::get('/escolas/{escola}', [SchoolController::class, 'show'])->whereUuid('escola')->name('schools.show');
    Route::get('/escolas/{escola}/pendencias', [SchoolPendenciesController::class, 'index'])->whereUuid('escola')->name('school.pendencies.index');
    Route::get('/escolas/{escola}/alertas-faltas', [SchoolAbsenceAlertsController::class, 'index'])->whereUuid('escola')->name('school.absences.index');
    // Servidores da unidade: leitura da escola, escrita somente para gestão com 2FA.
    Route::get('/escolas/{escola}/calendario', [SchoolCalendarController::class, 'index'])->whereUuid('escola')->name('school.calendar.index');
    Route::post('/escolas/{escola}/calendario', [SchoolCalendarController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.calendar.store');
    Route::post('/escolas/{escola}/calendario/feriados-nacionais', [SchoolCalendarController::class, 'addNationalHolidays'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.calendar.national-holidays');
    Route::delete('/escolas/{escola}/calendario/{dia}', [SchoolCalendarController::class, 'destroy'])->whereUuid('escola')->whereUuid('dia')->middleware(RequireConfirmedTwoFactor::class)->name('school.calendar.destroy');
    Route::get('/escolas/{escola}/servidores', [SchoolStaffController::class, 'index'])->whereUuid('escola')->name('school.staff.index');
    Route::get('/escolas/{escola}/servidores/novo', [SchoolStaffController::class, 'create'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.create');
    Route::post('/escolas/{escola}/servidores', [SchoolStaffController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.store');
    Route::get('/escolas/{escola}/servidores/{servidor}/editar', [SchoolStaffController::class, 'edit'])->whereUuid('escola')->whereUuid('servidor')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.edit');
    Route::put('/escolas/{escola}/servidores/{servidor}', [SchoolStaffController::class, 'update'])->whereUuid('escola')->whereUuid('servidor')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.update');
    Route::get('/escolas/{escola}/servidores/{servidor}/censo', [StaffCensusController::class, 'edit'])->whereUuid('escola')->whereUuid('servidor')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.census.edit');
    Route::put('/escolas/{escola}/servidores/{servidor}/censo', [StaffCensusController::class, 'update'])->whereUuid('escola')->whereUuid('servidor')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.census.update');
    Route::get('/escolas/{escola}/servidores/{servidor}/arquivar', [SchoolStaffController::class, 'removeForm'])->whereUuid('escola')->whereUuid('servidor')->name('school.staff.remove.form');
    Route::delete('/escolas/{escola}/servidores/{servidor}', [SchoolStaffController::class, 'destroy'])->whereUuid('escola')->whereUuid('servidor')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.staff.destroy');
    Route::get('/escolas/{escola}/turmas', [SchoolClassController::class, 'index'])->whereUuid('escola')->name('school.classes.index');
    Route::get('/escolas/{escola}/turmas/nova', [SchoolClassController::class, 'create'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.create');
    Route::get('/escolas/{escola}/turmas/{turma}', [SchoolClassController::class, 'show'])->whereUuid('escola')->whereUuid('turma')->name('school.classes.show');
    Route::post('/escolas/{escola}/turmas', [SchoolClassController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.store');
    Route::get('/escolas/{escola}/turmas/{turma}/editar', [SchoolClassController::class, 'edit'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.edit');
    Route::put('/escolas/{escola}/turmas/{turma}', [SchoolClassController::class, 'update'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.update');
    // Dados da escola para o Censo Escolar (registro 00 do Educacenso).
    Route::get('/escolas/{escola}/censo/escola', [SchoolCensusController::class, 'edit'])->whereUuid('escola')->name('school.census.school.edit');
    Route::put('/escolas/{escola}/censo/escola', [SchoolCensusController::class, 'update'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.census.school.update');
    // Infraestrutura anual da escola (registro 10 do Educacenso).
    Route::get('/escolas/{escola}/censo/infraestrutura', [SchoolInfrastructureCensusController::class, 'edit'])->whereUuid('escola')->name('school.census.infrastructure.edit');
    Route::put('/escolas/{escola}/censo/infraestrutura', [SchoolInfrastructureCensusController::class, 'update'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.census.infrastructure.update');
    // Dados das turmas para o Censo Escolar (registro 20 do Educacenso).
    Route::get('/escolas/{escola}/censo/turmas', [ClassCensusController::class, 'index'])->whereUuid('escola')->name('school.census.classes.index');
    Route::get('/escolas/{escola}/censo/alunos', [StudentsCensusController::class, 'index'])->whereUuid('escola')->name('school.census.students.index');
    Route::post('/escolas/{escola}/censo/turmas/lote', [ClassCensusController::class, 'batch'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.census.classes.batch');
    Route::get('/escolas/{escola}/turmas/{turma}/censo', [ClassCensusController::class, 'edit'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.census.classes.edit');
    Route::put('/escolas/{escola}/turmas/{turma}/censo', [ClassCensusController::class, 'update'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.census.classes.update');
    // Notas do Ensino Fundamental, com ajuste excepcional de período fechado.
    Route::get('/escolas/{escola}/turmas/{turma}/notas', [ClassGradesController::class, 'index'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.classes.grades.index');
    Route::post('/escolas/{escola}/turmas/{turma}/notas', [ClassGradesController::class, 'store'])
        ->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)
        ->name('school.classes.grades.store');
    Route::post('/escolas/{escola}/turmas/{turma}/notas/corrigir', [ClassGradesController::class, 'correct'])
        ->whereUuid('escola')->whereUuid('turma')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.classes.grades.correct');
    // Fechamento final, sempre com confirmação humana da escola.
    Route::get('/escolas/{escola}/turmas/{turma}/renovacao', [EnrollmentRenewalController::class, 'index'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.classes.renewal.index');
    Route::post('/escolas/{escola}/turmas/{turma}/renovacao', [EnrollmentRenewalController::class, 'store'])
        ->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.classes.renewal.store');
    Route::get('/escolas/{escola}/turmas/{turma}/resultado-final', [ClassYearEndController::class, 'index'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.classes.year-end.index');
    Route::post('/escolas/{escola}/turmas/{turma}/resultado-final/confirmar', [ClassYearEndController::class, 'confirm'])
        ->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)
        ->name('school.classes.year-end.confirm');
    Route::post('/escolas/{escola}/turmas/{turma}/resultado-final/fechar', [ClassYearEndController::class, 'close'])
        ->whereUuid('escola')->whereUuid('turma')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.classes.year-end.close');
    Route::post('/escolas/{escola}/turmas/{turma}/resultado-final/reabrir', [ClassYearEndController::class, 'reopen'])
        ->whereUuid('escola')->whereUuid('turma')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.classes.year-end.reopen');
    Route::get('/escolas/{escola}/turmas/{turma}/ata-final', [ClassYearEndController::class, 'minutes'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.classes.year-end.minutes');
    Route::post('/escolas/{escola}/turmas/{turma}/professores', [ClassTeacherAssignmentController::class, 'store'])
        ->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.teachers.store');
    Route::post('/escolas/{escola}/turmas/{turma}/professores/{atribuicao}/encerrar', [ClassTeacherAssignmentController::class, 'end'])
        ->whereUuid('escola')->whereUuid('turma')->whereUuid('atribuicao')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.teachers.end');
    // Presença diária: consulta da escola registra hoje; correção histórica requer gestão e 2FA recente.
    Route::get('/escolas/{escola}/turmas/{turma}/chamada', [SchoolAttendanceController::class, 'index'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.classes.attendance.index');
    Route::get('/escolas/{escola}/turmas/{turma}/frequencia-mensal', [SchoolMonthlyAttendanceReportController::class, 'index'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.classes.attendance.monthly');
    Route::post('/escolas/{escola}/turmas/{turma}/chamada', [SchoolAttendanceController::class, 'store'])
        ->whereUuid('escola')->whereUuid('turma')->name('school.classes.attendance.store');
    Route::post('/escolas/{escola}/turmas/{turma}/chamada/corrigir', [SchoolAttendanceController::class, 'correct'])
        ->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.classes.attendance.correct');
    Route::get('/escolas/{escola}/transporte/rotas', [SchoolTransportRoutesController::class, 'index'])->whereUuid('escola')->name('school.transport.routes.index');
    Route::get('/escolas/{escola}/censo/transporte', [TransportCensusController::class, 'edit'])->whereUuid('escola')->name('school.census.transport.edit');
    Route::put('/escolas/{escola}/censo/transporte', [TransportCensusController::class, 'update'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.census.transport.update');
    Route::get('/escolas/{escola}/matriculas', [SchoolEnrollmentController::class, 'index'])->whereUuid('escola')->name('school.enrollments.index');
    Route::get('/escolas/{escola}/importacoes/transporte', [ConfirmedTransportImportController::class, 'index'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.transport.index');
    Route::post('/escolas/{escola}/importacoes/transporte/previa', [ConfirmedTransportImportController::class, 'preview'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:6,1'])->name('school.imports.transport.preview');
    Route::post('/escolas/{escola}/importacoes/transporte/confirmar', [ConfirmedTransportImportController::class, 'confirm'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.transport.confirm');
    Route::get('/escolas/{escola}/importacoes/alunos', [ConfirmedStudentImportController::class, 'index'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.students.index');
    Route::post('/escolas/{escola}/importacoes/alunos/previa', [ConfirmedStudentImportController::class, 'preview'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:6,1'])->name('school.imports.students.preview');
    Route::post('/escolas/{escola}/importacoes/alunos/confirmar', [ConfirmedStudentImportController::class, 'confirm'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.students.confirm');
    Route::get('/escolas/{escola}/alunos', [SchoolStudentController::class, 'index'])->whereUuid('escola')->name('school.students.index');
    Route::get('/escolas/{escola}/alunos/cep/{cep}', [PostalCodeLookupController::class, 'show'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:20,1'])->name('school.students.postal.lookup');
    Route::get('/escolas/{escola}/alunos/{aluno}', [SchoolStudentController::class, 'show'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.show');

    // Emissão, impressão e revogação ficam restritas à gestão da escola.
    Route::get('/escolas/{escola}/alunos/{aluno}/declaracoes/nova', [EnrollmentDeclarationController::class, 'create'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.declarations.create');
    Route::post('/escolas/{escola}/alunos/{aluno}/declaracoes', [EnrollmentDeclarationController::class, 'store'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.declarations.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/declaracoes/{documento}', [EnrollmentDeclarationController::class, 'show'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('documento')->name('school.students.declarations.show');
    Route::post('/escolas/{escola}/alunos/{aluno}/declaracoes/{documento}/revogar', [EnrollmentDeclarationController::class, 'revoke'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('documento')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.students.declarations.revoke');

    // Histórico escolar com anos anteriores digitados e snapshot verificável.
    Route::get('/escolas/{escola}/alunos/{aluno}/historico-escolar', [SchoolTranscriptController::class, 'index'])
        ->whereUuid('escola')->whereUuid('aluno')->name('school.students.transcripts.index');
    Route::post('/escolas/{escola}/alunos/{aluno}/historico-escolar/anteriores', [SchoolTranscriptController::class, 'savePrevious'])
        ->whereUuid('escola')->whereUuid('aluno')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.students.transcripts.previous.save');
    Route::post('/escolas/{escola}/alunos/{aluno}/historico-escolar/anteriores/{registro}/excluir', [SchoolTranscriptController::class, 'archivePrevious'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('registro')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.students.transcripts.previous.archive');
    Route::post('/escolas/{escola}/alunos/{aluno}/historico-escolar/emitir', [SchoolTranscriptController::class, 'issue'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)
        ->name('school.students.transcripts.issue');
    Route::get('/escolas/{escola}/alunos/{aluno}/historico-escolar/documentos/{documento}', [SchoolTranscriptController::class, 'show'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('documento')
        ->name('school.students.transcripts.show');
    Route::post('/escolas/{escola}/alunos/{aluno}/historico-escolar/documentos/{documento}/revogar', [SchoolTranscriptController::class, 'revoke'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('documento')
        ->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.students.transcripts.revoke');

    // Responsáveis: cadastro municipal, vínculo por aluno e gravação protegida pelo segundo fator.
    Route::get('/escolas/{escola}/alunos/{aluno}/responsaveis/novo', [GuardianController::class, 'create'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.guardians.create');
    Route::post('/escolas/{escola}/alunos/{aluno}/responsaveis', [GuardianController::class, 'store'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.guardians.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}/editar', [GuardianController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.guardians.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}', [GuardianController::class, 'update'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.guardians.update');

    // Desvincular não apaga o responsável municipal; exige uma confirmação recente do segundo fator.
    Route::get('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}/desvincular', [GuardianController::class, 'unlinkForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->name('school.students.guardians.unlink.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}/desvincular', [GuardianController::class, 'unlink'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.students.guardians.unlink');

    // Localização no mapa (residência do aluno e escola): só quem gere a escola; gravação com segundo fator.
    Route::get('/escolas/{escola}/localizacao', [GeoLocationController::class, 'editSchool'])->whereUuid('escola')->name('schools.geo.edit');
    Route::get('/escolas/{escola}/localizacao/endereco', [GeoLocationController::class, 'reverseSchool'])->whereUuid('escola')->middleware('throttle:20,1')->name('schools.geo.reverse');
    Route::post('/escolas/{escola}/localizacao', [GeoLocationController::class, 'updateSchool'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('schools.geo.update');
    Route::get('/escolas/{escola}/alunos/{aluno}/localizacao', [GeoLocationController::class, 'editStudent'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.geo.edit');
    Route::post('/escolas/{escola}/alunos/{aluno}/localizacao', [GeoLocationController::class, 'updateStudent'])->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.geo.update');

    // Saída de alunos (portão): consulta da escola basta; registra a quem o aluno foi entregue.
    Route::get('/escolas/{escola}/saida', [StudentExitController::class, 'index'])->whereUuid('escola')->name('school.exits.index');
    Route::post('/escolas/{escola}/saida/{aluno}', [StudentExitController::class, 'store'])->whereUuid('escola')->whereUuid('aluno')->name('school.exits.store');
    // Ficha de saúde: gestão vê e edita (2FA); quem consulta vê só alertas graves na ficha e na chamada.
    Route::get('/escolas/{escola}/alunos/{aluno}/boletim', [StudentReportCardController::class, 'show'])
        ->whereUuid('escola')->whereUuid('aluno')->name('school.students.report-card.show');
    // Dados do aluno para o Censo (registro 30): mesmo acesso do cadastro do aluno.
    Route::get('/escolas/{escola}/alunos/{aluno}/censo', [StudentCensusController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.census.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}/censo', [StudentCensusController::class, 'update'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.census.update');
    // Educação especial e AEE (Censo): mesmo acesso da ficha de saúde.
    Route::get('/escolas/{escola}/alunos/{aluno}/educacao-especial', [StudentSpecialEducationController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.special-education.edit');
    Route::post('/escolas/{escola}/alunos/{aluno}/educacao-especial', [StudentSpecialEducationController::class, 'update'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)
        ->name('school.students.special-education.update');
    Route::get('/escolas/{escola}/alunos/{aluno}/saude', [StudentHealthController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.health.edit');
    Route::post('/escolas/{escola}/alunos/{aluno}/saude', [StudentHealthController::class, 'updateProfile'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.health.update');
    Route::post('/escolas/{escola}/alunos/{aluno}/saude/alertas', [StudentHealthController::class, 'storeAlert'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.health.alerts.store');
    Route::post('/escolas/{escola}/alunos/{aluno}/saude/alertas/{alerta}/remover', [StudentHealthController::class, 'destroyAlert'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('alerta')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.health.alerts.destroy');
    Route::post('/escolas/{escola}/saida/registro/{saida}/desfazer', [StudentExitController::class, 'cancel'])->whereUuid('escola')->whereUuid('saida')->name('school.exits.cancel');

    // Foto de identificação: consulta com a permissão da escola; envio e remoção com segundo fator.
    Route::get('/escolas/{escola}/fotos/{tipo}/{id}', [IdentificationPhotoController::class, 'show'])
        ->whereUuid('escola')->whereIn('tipo', IdentificationPhotoController::TYPES)->whereUuid('id')->name('school.photos.show');
    Route::post('/escolas/{escola}/fotos/{tipo}/{id}', [IdentificationPhotoController::class, 'update'])
        ->whereUuid('escola')->whereIn('tipo', IdentificationPhotoController::TYPES)->whereUuid('id')->middleware(RequireConfirmedTwoFactor::class)->name('school.photos.update');
    Route::post('/escolas/{escola}/fotos/{tipo}/{id}/remover', [IdentificationPhotoController::class, 'destroy'])
        ->whereUuid('escola')->whereIn('tipo', IdentificationPhotoController::TYPES)->whereUuid('id')->middleware(RequireConfirmedTwoFactor::class)->name('school.photos.destroy');

    // Pessoas adicionais para retirada: responsáveis já autorizados por vínculo familiar.
    Route::get('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/nova', [StudentPickupAuthorizationController::class, 'create'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.pickups.create');
    Route::post('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas', [StudentPickupAuthorizationController::class, 'store'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.pickups.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}/editar', [StudentPickupAuthorizationController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.pickups.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}', [StudentPickupAuthorizationController::class, 'update'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.pickups.update');
    Route::get('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}/remover', [StudentPickupAuthorizationController::class, 'removeForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->name('school.students.pickups.remove.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}/remover', [StudentPickupAuthorizationController::class, 'remove'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.students.pickups.remove');

    // Gravação: permissão de gestão na escola (no controlador) e segundo fator confirmado.
    Route::get('/escolas/{escola}/alunos/novo', [StudentRecordController::class, 'create'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.create');
    Route::post('/escolas/{escola}/alunos', [StudentRecordController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/editar', [StudentRecordController::class, 'edit'])->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}', [StudentRecordController::class, 'update'])->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.update');

    // Movimentações são confirmadas em GET e gravadas somente com segundo fator.
    // A exclusão de cadastro é ação delicada: pede o código digitado nos últimos 15 minutos.
    Route::get('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/transferir', [EnrollmentMovementController::class, 'transferForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class)->name('school.enrollments.transfer.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/transferir', [EnrollmentMovementController::class, 'transfer'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class)->name('school.enrollments.transfer');
    Route::get('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/encerrar', [EnrollmentMovementController::class, 'endForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class)->name('school.enrollments.end.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/encerrar', [EnrollmentMovementController::class, 'end'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class)->name('school.enrollments.end');
    Route::get('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/excluir-cadastro', [EnrollmentMovementController::class, 'deleteForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.enrollments.delete-registration.form');
    Route::delete('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/excluir-cadastro', [EnrollmentMovementController::class, 'deleteRegistration'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.enrollments.delete-registration');
});
