<?php

/*
| Cadastros e Configurações — dados estruturais do sistema.
| Incluído em routes/web.php, dentro do grupo ['auth', 'activo'].
|
| A verificação de permissões é feita aqui (middleware "permissao") e, nos casos
| que dependem da jurisdição (escola do município, registo da escola ou de
| referência provincial), novamente nos controllers.
*/

use App\Http\Controllers\Cadastros\AnoLectivoController;
use App\Http\Controllers\Cadastros\ClasseController;
use App\Http\Controllers\Cadastros\DisciplinaController;
use App\Http\Controllers\Cadastros\NivelEnsinoController;
use App\Http\Controllers\Cadastros\PeriodoAvaliacaoController;
use App\Http\Controllers\Rede\CatalogoController;
use App\Http\Controllers\Rede\EscolaController;
use App\Http\Controllers\Rede\MunicipioController;
use Illuminate\Support\Facades\Route;

/*
| Municípios
*/
Route::middleware('permissao:municipios.gerir')->group(function () {
    Route::get('/municipios/criar', [MunicipioController::class, 'create'])->name('municipios.create');
    Route::post('/municipios', [MunicipioController::class, 'store'])->name('municipios.store');
    Route::get('/municipios/{municipio}/editar', [MunicipioController::class, 'edit'])->name('municipios.edit');
    Route::put('/municipios/{municipio}', [MunicipioController::class, 'update'])->name('municipios.update');
    Route::patch('/municipios/{municipio}/estado', [MunicipioController::class, 'alternarEstado'])->name('municipios.estado');
});
Route::middleware('permissao:municipios.ver')->group(function () {
    Route::get('/municipios', [MunicipioController::class, 'index'])->name('municipios.index');
    Route::get('/municipios/{municipio}', [MunicipioController::class, 'show'])->name('municipios.show');
});

/*
| Escolas (a Direcção Municipal só gere as escolas do seu município — validado no controller)
*/
Route::middleware('permissao:escolas.gerir')->group(function () {
    Route::get('/escolas/criar', [EscolaController::class, 'create'])->name('escolas.create');
    Route::post('/escolas', [EscolaController::class, 'store'])->name('escolas.store');
    Route::get('/escolas/{escola}/editar', [EscolaController::class, 'edit'])->name('escolas.edit');
    Route::put('/escolas/{escola}', [EscolaController::class, 'update'])->name('escolas.update');
    Route::patch('/escolas/{escola}/estado', [EscolaController::class, 'alternarEstado'])->name('escolas.estado');
});
Route::middleware('permissao:escolas.ver')->group(function () {
    Route::get('/escolas', [EscolaController::class, 'index'])->name('escolas.index');
    Route::get('/escolas/{escola}', [EscolaController::class, 'show'])->name('escolas.show');
});

/*
| Tipologias institucionais
*/
Route::middleware('permissao:rede.catalogos')->group(function () {
    Route::get('/tipologias', [CatalogoController::class, 'index'])->name('tipologias.index')->defaults('catalogo', 'tipologias');
    Route::post('/tipologias', [CatalogoController::class, 'store'])->name('tipologias.store')->defaults('catalogo', 'tipologias');
    Route::put('/tipologias/{id}', [CatalogoController::class, 'update'])->whereNumber('id')->name('tipologias.update')->defaults('catalogo', 'tipologias');
    Route::patch('/tipologias/{id}/estado', [CatalogoController::class, 'alternarEstado'])->whereNumber('id')->name('tipologias.estado')->defaults('catalogo', 'tipologias');
});

Route::prefix('cadastros')->name('cadastros.')->group(function () {
    /*
    | Disciplinas: referência provincial (academico.global) ou da escola activa (academico.configurar).
    */
    Route::middleware('permissao:cadastros.ver|academico.configurar|academico.global')->group(function () {
        Route::get('/disciplinas', [DisciplinaController::class, 'index'])->name('disciplinas.index');
        Route::get('/disciplinas/{id}', [DisciplinaController::class, 'show'])->whereNumber('id')->name('disciplinas.show');
    });
    Route::middleware('permissao:academico.configurar|academico.global')->group(function () {
        Route::post('/disciplinas', [DisciplinaController::class, 'store'])->name('disciplinas.store');
        Route::put('/disciplinas/{id}', [DisciplinaController::class, 'update'])->whereNumber('id')->name('disciplinas.update');
        Route::patch('/disciplinas/{id}/estado', [DisciplinaController::class, 'alternarEstado'])->whereNumber('id')->name('disciplinas.estado');
    });

    /*
    | Níveis de ensino
    */
    Route::middleware('permissao:cadastros.ver|rede.catalogos')->group(function () {
        Route::get('/niveis-ensino', [NivelEnsinoController::class, 'index'])->name('niveis.index');
        Route::get('/niveis-ensino/{nivel}', [NivelEnsinoController::class, 'show'])->name('niveis.show');
    });
    Route::middleware('permissao:rede.catalogos')->group(function () {
        Route::post('/niveis-ensino', [NivelEnsinoController::class, 'store'])->name('niveis.store');
        Route::put('/niveis-ensino/{nivel}', [NivelEnsinoController::class, 'update'])->name('niveis.update');
        Route::patch('/niveis-ensino/{nivel}/estado', [NivelEnsinoController::class, 'alternarEstado'])->name('niveis.estado');
    });

    /*
    | Classes, ciclos, anos lectivos e períodos: referências provinciais.
    */
    Route::middleware('permissao:cadastros.ver|academico.global')->group(function () {
        Route::get('/classes', [ClasseController::class, 'index'])->name('classes.index');
        Route::get('/classes/{classe}', [ClasseController::class, 'show'])->name('classes.show');
        Route::get('/anos-lectivos', [AnoLectivoController::class, 'index'])->name('anos.index');
        Route::get('/anos-lectivos/{ano}', [AnoLectivoController::class, 'show'])->name('anos.show');
        Route::get('/periodos', [PeriodoAvaliacaoController::class, 'index'])->name('periodos.index');
        Route::get('/periodos/{trimestre}', [PeriodoAvaliacaoController::class, 'show'])->name('periodos.show');
    });
    Route::middleware('permissao:academico.global')->group(function () {
        Route::post('/classes', [ClasseController::class, 'store'])->name('classes.store');
        Route::put('/classes/{classe}', [ClasseController::class, 'update'])->name('classes.update');
        Route::patch('/classes/{classe}/estado', [ClasseController::class, 'alternarEstado'])->name('classes.estado');
        Route::post('/ciclos', [ClasseController::class, 'guardarCiclo'])->name('ciclos.store');
        Route::put('/ciclos/{ciclo}', [ClasseController::class, 'guardarCiclo'])->name('ciclos.update');
        Route::patch('/ciclos/{ciclo}/estado', [ClasseController::class, 'alternarCiclo'])->name('ciclos.estado');

        Route::post('/anos-lectivos', [AnoLectivoController::class, 'store'])->name('anos.store');
        Route::put('/anos-lectivos/{ano}', [AnoLectivoController::class, 'update'])->name('anos.update');
        Route::patch('/anos-lectivos/{ano}/actual', [AnoLectivoController::class, 'definirActual'])->name('anos.actual');

        Route::post('/periodos', [PeriodoAvaliacaoController::class, 'store'])->name('periodos.store');
        Route::put('/periodos/{trimestre}', [PeriodoAvaliacaoController::class, 'update'])->name('periodos.update');
    });
});
