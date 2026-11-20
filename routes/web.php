<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DemonstrationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolStudentController;
use App\Http\Middleware\ResolveMunicipality;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/saude', fn () => response()->json(['status' => 'ok']));

Route::get('/demonstracao', [DemonstrationController::class, 'index'])->name('demo');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:astuti-login')->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('/m/{municipio}')->middleware(['auth', ResolveMunicipality::class])->group(function (): void {
    Route::get('/escolas', [SchoolController::class, 'index'])->name('schools.index');
    Route::get('/escolas/{escola}', [SchoolController::class, 'show'])->whereUuid('escola')->name('schools.show');
    Route::get('/escolas/{escola}/turmas', [SchoolClassController::class, 'index'])->whereUuid('escola')->name('school.classes.index');
    Route::get('/escolas/{escola}/alunos', [SchoolStudentController::class, 'index'])->whereUuid('escola')->name('school.students.index');
});
