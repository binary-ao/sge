<?php

/*
| Fase 6 — Faltas, pagamentos, recibos e declarações (sempre na escola activa).
| Incluído em routes/web.php, dentro do grupo ['auth', 'activo'].
*/

use App\Http\Controllers\Administrativo\DeclaracaoController;
use App\Http\Controllers\Administrativo\FaltaController;
use App\Http\Controllers\Administrativo\PagamentoController;
use App\Http\Controllers\Administrativo\ServicoPagavelController;
use Illuminate\Support\Facades\Route;

Route::middleware('escola.activa')->group(function () {
    // Faltas
    Route::middleware('permissao:faltas.ver|faltas.registar')->group(function () {
        Route::get('/faltas', [FaltaController::class, 'index'])->name('faltas.index');
        Route::get('/faltas/justificativos/{justificativo}/documento', [FaltaController::class, 'documento'])->name('faltas.documento');
    });
    Route::middleware('permissao:faltas.registar')->group(function () {
        Route::get('/faltas/chamada', [FaltaController::class, 'chamada'])->name('faltas.chamada');
        Route::post('/faltas/chamada', [FaltaController::class, 'guardarChamada'])->name('faltas.chamada.guardar');
    });
    Route::post('/faltas/{falta}/justificativo', [FaltaController::class, 'justificar'])->middleware('permissao:faltas.registar|faltas.justificar')->name('faltas.justificar');
    Route::patch('/faltas/justificativos/{justificativo}', [FaltaController::class, 'analisar'])->middleware('permissao:faltas.justificar')->name('faltas.analisar');

    // Pagamentos e recibos
    Route::middleware('permissao:pagamentos.ver')->group(function () {
        Route::get('/pagamentos', [PagamentoController::class, 'index'])->name('pagamentos.index');
        Route::get('/pagamentos/em-atraso', [PagamentoController::class, 'atraso'])->name('pagamentos.atraso');
        Route::get('/recibos/{recibo}', [PagamentoController::class, 'recibo'])->name('pagamentos.recibo');
        Route::get('/recibos/{recibo}/pdf', [PagamentoController::class, 'reciboPdf'])->name('pagamentos.recibo.pdf');
    });
    Route::middleware('permissao:pagamentos.registar')->group(function () {
        Route::get('/pagamentos/registar', [PagamentoController::class, 'create'])->name('pagamentos.create');
        Route::post('/pagamentos', [PagamentoController::class, 'store'])->name('pagamentos.store');
    });
    Route::patch('/recibos/{recibo}/anular', [PagamentoController::class, 'anular'])->middleware('permissao:pagamentos.anular')->name('pagamentos.anular');

    Route::middleware('permissao:servicos.gerir')->group(function () {
        Route::get('/servicos', [ServicoPagavelController::class, 'index'])->name('servicos.index');
        Route::post('/servicos', [ServicoPagavelController::class, 'store'])->name('servicos.store');
        Route::put('/servicos/{servico}', [ServicoPagavelController::class, 'update'])->name('servicos.update');
        Route::patch('/servicos/{servico}/estado', [ServicoPagavelController::class, 'alternarEstado'])->name('servicos.estado');
    });

    // Declarações
    Route::middleware('permissao:declaracoes.emitir')->group(function () {
        Route::get('/declaracoes', [DeclaracaoController::class, 'index'])->name('declaracoes.index');
        Route::get('/declaracoes/emitir', [DeclaracaoController::class, 'create'])->name('declaracoes.create');
        Route::post('/declaracoes', [DeclaracaoController::class, 'store'])->name('declaracoes.store');
        Route::get('/declaracoes/{declaracao}/pdf', [DeclaracaoController::class, 'pdf'])->name('declaracoes.pdf');
    });
});
