<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ClassTeacherAssignmentController;
use App\Http\Controllers\ConfirmedStudentImportController;
use App\Http\Controllers\ConfirmedTransportImportController;
use App\Http\Controllers\DemonstrationController;
use App\Http\Controllers\EnrollmentMovementController;
use App\Http\Controllers\GeoLocationController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IdentificationPhotoController;
use App\Http\Controllers\PostalCodeLookupController;
use App\Http\Controllers\SchoolAbsenceAlertsController;
use App\Http\Controllers\SchoolAttendanceController;
use App\Http\Controllers\SchoolCalendarController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolEnrollmentController;
use App\Http\Controllers\SchoolMonthlyAttendanceReportController;
use App\Http\Controllers\SchoolPendenciesController;
use App\Http\Controllers\SchoolStaffController;
use App\Http\Controllers\SchoolStudentController;
use App\Http\Controllers\StudentExitController;
use App\Http\Controllers\StudentHealthController;
use App\Http\Controllers\StudentPickupAuthorizationController;
use App\Http\Controllers\StudentRecordController;
use App\Http\Middleware\RequireConfirmedTwoFactor;
use App\Http\Middleware\RequireLoginTwoFactor;
use App\Http\Middleware\ResolveMunicipality;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->middleware(RequireLoginTwoFactor::class)->name('home');

Route::get('/saude', fn () => response()->json(['status' => 'ok']));

Route::get('/demonstracao', [DemonstrationController::class, 'index'])->name('demo');

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
    Route::get('/escolas/{escola}/servidores/novo', [SchoolStaffController::class, 'create'])->whereUuid('escola')->name('school.staff.create');
    Route::post('/escolas/{escola}/servidores', [SchoolStaffController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.store');
    Route::get('/escolas/{escola}/servidores/{servidor}/editar', [SchoolStaffController::class, 'edit'])->whereUuid('escola')->whereUuid('servidor')->name('school.staff.edit');
    Route::put('/escolas/{escola}/servidores/{servidor}', [SchoolStaffController::class, 'update'])->whereUuid('escola')->whereUuid('servidor')->middleware(RequireConfirmedTwoFactor::class)->name('school.staff.update');
    Route::get('/escolas/{escola}/servidores/{servidor}/arquivar', [SchoolStaffController::class, 'removeForm'])->whereUuid('escola')->whereUuid('servidor')->name('school.staff.remove.form');
    Route::delete('/escolas/{escola}/servidores/{servidor}', [SchoolStaffController::class, 'destroy'])->whereUuid('escola')->whereUuid('servidor')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.staff.destroy');
    Route::get('/escolas/{escola}/turmas', [SchoolClassController::class, 'index'])->whereUuid('escola')->name('school.classes.index');
    Route::get('/escolas/{escola}/turmas/nova', [SchoolClassController::class, 'create'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.create');
    Route::get('/escolas/{escola}/turmas/{turma}', [SchoolClassController::class, 'show'])->whereUuid('escola')->whereUuid('turma')->name('school.classes.show');
    Route::post('/escolas/{escola}/turmas', [SchoolClassController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.store');
    Route::get('/escolas/{escola}/turmas/{turma}/editar', [SchoolClassController::class, 'edit'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.edit');
    Route::put('/escolas/{escola}/turmas/{turma}', [SchoolClassController::class, 'update'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.update');
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
    Route::get('/escolas/{escola}/matriculas', [SchoolEnrollmentController::class, 'index'])->whereUuid('escola')->name('school.enrollments.index');
    Route::get('/escolas/{escola}/importacoes/transporte', [ConfirmedTransportImportController::class, 'index'])->whereUuid('escola')->name('school.imports.transport.index');
    Route::post('/escolas/{escola}/importacoes/transporte/previa', [ConfirmedTransportImportController::class, 'preview'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:6,1'])->name('school.imports.transport.preview');
    Route::post('/escolas/{escola}/importacoes/transporte/confirmar', [ConfirmedTransportImportController::class, 'confirm'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.transport.confirm');
    Route::get('/escolas/{escola}/importacoes/alunos', [ConfirmedStudentImportController::class, 'index'])->whereUuid('escola')->name('school.imports.students.index');
    Route::post('/escolas/{escola}/importacoes/alunos/previa', [ConfirmedStudentImportController::class, 'preview'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:6,1'])->name('school.imports.students.preview');
    Route::post('/escolas/{escola}/importacoes/alunos/confirmar', [ConfirmedStudentImportController::class, 'confirm'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.students.confirm');
    Route::get('/escolas/{escola}/alunos', [SchoolStudentController::class, 'index'])->whereUuid('escola')->name('school.students.index');
    Route::get('/escolas/{escola}/alunos/cep/{cep}', [PostalCodeLookupController::class, 'show'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:20,1'])->name('school.students.postal.lookup');
    Route::get('/escolas/{escola}/alunos/{aluno}', [SchoolStudentController::class, 'show'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.show');

    // Responsáveis: cadastro municipal, vínculo por aluno e gravação protegida pelo segundo fator.
    Route::get('/escolas/{escola}/alunos/{aluno}/responsaveis/novo', [GuardianController::class, 'create'])
        ->whereUuid('escola')->whereUuid('aluno')->name('school.students.guardians.create');
    Route::post('/escolas/{escola}/alunos/{aluno}/responsaveis', [GuardianController::class, 'store'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.guardians.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}/editar', [GuardianController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->name('school.students.guardians.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}', [GuardianController::class, 'update'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.guardians.update');

    // Desvincular não apaga o responsável municipal; exige uma confirmação recente do segundo fator.
    Route::get('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}/desvincular', [GuardianController::class, 'unlinkForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->name('school.students.guardians.unlink.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/responsaveis/{vinculo}/desvincular', [GuardianController::class, 'unlink'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('vinculo')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.students.guardians.unlink');

    // Localização no mapa (residência do aluno e escola): só quem gere a escola; gravação com segundo fator.
    Route::get('/escolas/{escola}/localizacao', [GeoLocationController::class, 'editSchool'])->whereUuid('escola')->name('schools.geo.edit');
    Route::post('/escolas/{escola}/localizacao', [GeoLocationController::class, 'updateSchool'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('schools.geo.update');
    Route::get('/escolas/{escola}/alunos/{aluno}/localizacao', [GeoLocationController::class, 'editStudent'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.geo.edit');
    Route::post('/escolas/{escola}/alunos/{aluno}/localizacao', [GeoLocationController::class, 'updateStudent'])->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.geo.update');

    // Saída de alunos (portão): consulta da escola basta; registra a quem o aluno foi entregue.
    Route::get('/escolas/{escola}/saida', [StudentExitController::class, 'index'])->whereUuid('escola')->name('school.exits.index');
    Route::post('/escolas/{escola}/saida/{aluno}', [StudentExitController::class, 'store'])->whereUuid('escola')->whereUuid('aluno')->name('school.exits.store');
    // Ficha de saúde: gestão vê e edita (2FA); quem consulta vê só alertas graves na ficha e na chamada.
    Route::get('/escolas/{escola}/alunos/{aluno}/saude', [StudentHealthController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->name('school.students.health.edit');
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
        ->whereUuid('escola')->whereUuid('aluno')->name('school.students.pickups.create');
    Route::post('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas', [StudentPickupAuthorizationController::class, 'store'])
        ->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.pickups.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}/editar', [StudentPickupAuthorizationController::class, 'edit'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->name('school.students.pickups.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}', [StudentPickupAuthorizationController::class, 'update'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.pickups.update');
    Route::get('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}/remover', [StudentPickupAuthorizationController::class, 'removeForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->name('school.students.pickups.remove.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/pessoas-autorizadas/{autorizacao}/remover', [StudentPickupAuthorizationController::class, 'remove'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('autorizacao')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.students.pickups.remove');

    // Gravação: permissão de gestão na escola (no controlador) e segundo fator confirmado.
    Route::get('/escolas/{escola}/alunos/novo', [StudentRecordController::class, 'create'])->whereUuid('escola')->name('school.students.create');
    Route::post('/escolas/{escola}/alunos', [StudentRecordController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/editar', [StudentRecordController::class, 'edit'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}', [StudentRecordController::class, 'update'])->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.update');

    // Movimentações são confirmadas em GET e gravadas somente com segundo fator.
    // A exclusão de cadastro é ação delicada: pede o código digitado nos últimos 15 minutos.
    Route::get('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/transferir', [EnrollmentMovementController::class, 'transferForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->name('school.enrollments.transfer.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/transferir', [EnrollmentMovementController::class, 'transfer'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class)->name('school.enrollments.transfer');
    Route::get('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/encerrar', [EnrollmentMovementController::class, 'endForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->name('school.enrollments.end.form');
    Route::post('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/encerrar', [EnrollmentMovementController::class, 'end'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class)->name('school.enrollments.end');
    Route::get('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/excluir-cadastro', [EnrollmentMovementController::class, 'deleteForm'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.enrollments.delete-registration.form');
    Route::delete('/escolas/{escola}/alunos/{aluno}/matriculas/{matricula}/excluir-cadastro', [EnrollmentMovementController::class, 'deleteRegistration'])
        ->whereUuid('escola')->whereUuid('aluno')->whereUuid('matricula')->middleware(RequireConfirmedTwoFactor::class.':'.RequireConfirmedTwoFactor::RECENT)->name('school.enrollments.delete-registration');
});
