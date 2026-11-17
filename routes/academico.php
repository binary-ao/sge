<?php

/*
| Fase 3 — Configuração académica e gestão académica da escola.
| Incluído em routes/web.php, dentro do grupo ['auth', 'activo'].
*/

use App\Http\Controllers\Academico\CursoController;
use App\Http\Controllers\Academico\EstruturaController;
use App\Http\Controllers\Academico\PlanoCurricularController;
use App\Http\Controllers\Escola\AlunoController;
use App\Http\Controllers\Escola\AlunoDocumentoController;
use App\Http\Controllers\Escola\MatriculaController;
use App\Http\Controllers\Escola\ProfessorController;
use App\Http\Controllers\Escola\TurmaController;
use Illuminate\Support\Facades\Route;

/*
| Configuração académica
*/
Route::prefix('academico')->name('academico.')->group(function () {
    Route::middleware('permissao:academico.configurar|academico.global')->group(function () {
        Route::get('/', fn () => redirect()->route('academico.planos.index'))->name('index');
        Route::get('/turnos', [EstruturaController::class, 'index'])->name('turnos.index');

        Route::get('/cursos', [CursoController::class, 'index'])->name('cursos.index');
        Route::post('/cursos', [CursoController::class, 'store'])->name('cursos.store');
        Route::put('/cursos/{id}', [CursoController::class, 'update'])->whereNumber('id')->name('cursos.update');
        Route::patch('/cursos/{id}/estado', [CursoController::class, 'alternarEstado'])->whereNumber('id')->name('cursos.estado');

        Route::get('/planos', [PlanoCurricularController::class, 'index'])->name('planos.index');
        Route::get('/planos/criar', [PlanoCurricularController::class, 'create'])->name('planos.create');
        Route::post('/planos', [PlanoCurricularController::class, 'store'])->name('planos.store');
        Route::get('/planos/{id}', [PlanoCurricularController::class, 'show'])->whereNumber('id')->name('planos.show');
        Route::get('/planos/{id}/editar', [PlanoCurricularController::class, 'edit'])->whereNumber('id')->name('planos.edit');
        Route::put('/planos/{id}', [PlanoCurricularController::class, 'update'])->whereNumber('id')->name('planos.update');
    });

    // Turnos: referência provincial, apenas com "academico.global".
    // (Anos lectivos, classes, ciclos e disciplinas passaram para Cadastros e Configurações.)
    Route::middleware('permissao:academico.global')->group(function () {
        Route::post('/turnos', [EstruturaController::class, 'guardarTurno'])->name('turnos.store');
        Route::put('/turnos/{turno}', [EstruturaController::class, 'guardarTurno'])->name('turnos.update');
        Route::patch('/turnos/{turno}/estado', [EstruturaController::class, 'alternarTurno'])->name('turnos.estado');
    });
});

/*
| Gestão académica da escola (exige escola activa; dados isolados por escola)
*/
Route::middleware('escola.activa')->group(function () {
    // Professores
    Route::middleware('permissao:professores.gerir')->group(function () {
        Route::get('/professores/criar', [ProfessorController::class, 'create'])->name('professores.create');
        Route::post('/professores', [ProfessorController::class, 'store'])->name('professores.store');
        Route::get('/professores/{professor}/editar', [ProfessorController::class, 'edit'])->name('professores.edit');
        Route::put('/professores/{professor}', [ProfessorController::class, 'update'])->name('professores.update');
        Route::patch('/professores/{professor}/estado', [ProfessorController::class, 'alternarEstado'])->name('professores.estado');
    });
    Route::middleware('permissao:professores.ver')->group(function () {
        Route::get('/professores', [ProfessorController::class, 'index'])->name('professores.index');
        Route::get('/professores/{professor}', [ProfessorController::class, 'show'])->name('professores.show');
    });

    // Alunos
    Route::middleware('permissao:alunos.gerir')->group(function () {
        Route::get('/alunos/criar', [AlunoController::class, 'create'])->name('alunos.create');
        Route::post('/alunos', [AlunoController::class, 'store'])->name('alunos.store');
        Route::get('/alunos/{aluno}/editar', [AlunoController::class, 'edit'])->name('alunos.edit');
        Route::put('/alunos/{aluno}', [AlunoController::class, 'update'])->name('alunos.update');
        Route::patch('/alunos/{aluno}/situacao', [AlunoController::class, 'alterarEstado'])->name('alunos.estado');
        Route::post('/alunos/{aluno}/documentos', [AlunoDocumentoController::class, 'store'])->name('alunos.documentos.store');
        Route::delete('/alunos/{aluno}/documentos/{documento}', [AlunoDocumentoController::class, 'destroy'])->name('alunos.documentos.destroy');
    });
    Route::middleware('permissao:alunos.ver')->group(function () {
        Route::get('/alunos', [AlunoController::class, 'index'])->name('alunos.index');
        Route::get('/alunos/{aluno}', [AlunoController::class, 'show'])->name('alunos.show');
        Route::get('/alunos/{aluno}/documentos/{documento}', [AlunoDocumentoController::class, 'download'])->name('alunos.documentos.download');
    });

    // Matrículas
    Route::middleware('permissao:matriculas.gerir')->group(function () {
        Route::get('/matriculas/criar', [MatriculaController::class, 'create'])->name('matriculas.create');
        Route::post('/matriculas', [MatriculaController::class, 'store'])->name('matriculas.store');
        Route::get('/matriculas/{matricula}/editar', [MatriculaController::class, 'edit'])->name('matriculas.edit');
        Route::put('/matriculas/{matricula}', [MatriculaController::class, 'update'])->name('matriculas.update');
        Route::patch('/matriculas/{matricula}/anular', [MatriculaController::class, 'anular'])->name('matriculas.anular');
    });
    Route::get('/matriculas', [MatriculaController::class, 'index'])->middleware('permissao:matriculas.gerir|alunos.ver')->name('matriculas.index');

    // Turmas
    Route::middleware('permissao:turmas.gerir')->group(function () {
        Route::get('/turmas/criar', [TurmaController::class, 'create'])->name('turmas.create');
        Route::post('/turmas', [TurmaController::class, 'store'])->name('turmas.store');
        Route::get('/turmas/{turma}/editar', [TurmaController::class, 'edit'])->name('turmas.edit');
        Route::put('/turmas/{turma}', [TurmaController::class, 'update'])->name('turmas.update');
        Route::put('/turmas/{turma}/professores', [TurmaController::class, 'atribuirProfessores'])->name('turmas.professores');
    });
    Route::middleware('permissao:turmas.gerir|matriculas.gerir')->group(function () {
        Route::post('/turmas/{turma}/distribuir', [TurmaController::class, 'distribuir'])->name('turmas.distribuir');
        Route::delete('/turmas/{turma}/alunos/{matricula}', [TurmaController::class, 'retirarAluno'])->name('turmas.retirar');
    });
    Route::middleware('permissao:turmas.ver')->group(function () {
        Route::get('/turmas', [TurmaController::class, 'index'])->name('turmas.index');
        Route::get('/turmas/{turma}', [TurmaController::class, 'show'])->name('turmas.show');
    });
});
