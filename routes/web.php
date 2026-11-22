<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ConfirmedStudentImportController;
use App\Http\Controllers\ConfirmedTransportImportController;
use App\Http\Controllers\DemonstrationController;
use App\Http\Controllers\EnrollmentMovementController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolEnrollmentController;
use App\Http\Controllers\SchoolStudentController;
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
    Route::get('/escolas/{escola}/turmas', [SchoolClassController::class, 'index'])->whereUuid('escola')->name('school.classes.index');
    Route::get('/escolas/{escola}/turmas/nova', [SchoolClassController::class, 'create'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.create');
    Route::post('/escolas/{escola}/turmas', [SchoolClassController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.store');
    Route::get('/escolas/{escola}/turmas/{turma}/editar', [SchoolClassController::class, 'edit'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.edit');
    Route::put('/escolas/{escola}/turmas/{turma}', [SchoolClassController::class, 'update'])->whereUuid('escola')->whereUuid('turma')->middleware(RequireConfirmedTwoFactor::class)->name('school.classes.update');
    Route::get('/escolas/{escola}/matriculas', [SchoolEnrollmentController::class, 'index'])->whereUuid('escola')->name('school.enrollments.index');
    Route::get('/escolas/{escola}/importacoes/transporte', [ConfirmedTransportImportController::class, 'index'])->whereUuid('escola')->name('school.imports.transport.index');
    Route::post('/escolas/{escola}/importacoes/transporte/previa', [ConfirmedTransportImportController::class, 'preview'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:6,1'])->name('school.imports.transport.preview');
    Route::post('/escolas/{escola}/importacoes/transporte/confirmar', [ConfirmedTransportImportController::class, 'confirm'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.transport.confirm');
    Route::get('/escolas/{escola}/importacoes/alunos', [ConfirmedStudentImportController::class, 'index'])->whereUuid('escola')->name('school.imports.students.index');
    Route::post('/escolas/{escola}/importacoes/alunos/previa', [ConfirmedStudentImportController::class, 'preview'])->whereUuid('escola')->middleware([RequireConfirmedTwoFactor::class, 'throttle:6,1'])->name('school.imports.students.preview');
    Route::post('/escolas/{escola}/importacoes/alunos/confirmar', [ConfirmedStudentImportController::class, 'confirm'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.imports.students.confirm');
    Route::get('/escolas/{escola}/alunos', [SchoolStudentController::class, 'index'])->whereUuid('escola')->name('school.students.index');
    Route::get('/escolas/{escola}/alunos/{aluno}', [SchoolStudentController::class, 'show'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.show');

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
