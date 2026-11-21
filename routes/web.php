<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DemonstrationController;
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
    Route::get('/escolas/{escola}/matriculas', [SchoolEnrollmentController::class, 'index'])->whereUuid('escola')->name('school.enrollments.index');
    Route::get('/escolas/{escola}/alunos', [SchoolStudentController::class, 'index'])->whereUuid('escola')->name('school.students.index');
    Route::get('/escolas/{escola}/alunos/{aluno}', [SchoolStudentController::class, 'show'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.show');

    // Gravação: permissão de gestão na escola (no controlador) e segundo fator confirmado.
    Route::get('/escolas/{escola}/alunos/novo', [StudentRecordController::class, 'create'])->whereUuid('escola')->name('school.students.create');
    Route::post('/escolas/{escola}/alunos', [StudentRecordController::class, 'store'])->whereUuid('escola')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.store');
    Route::get('/escolas/{escola}/alunos/{aluno}/editar', [StudentRecordController::class, 'edit'])->whereUuid('escola')->whereUuid('aluno')->name('school.students.edit');
    Route::put('/escolas/{escola}/alunos/{aluno}', [StudentRecordController::class, 'update'])->whereUuid('escola')->whereUuid('aluno')->middleware(RequireConfirmedTwoFactor::class)->name('school.students.update');
});
