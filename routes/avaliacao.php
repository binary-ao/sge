<?php

/*
| Fase 4 — Avaliação das aprendizagens.
| Incluído em routes/web.php, dentro do grupo ['auth', 'activo'].
*/

use App\Http\Controllers\Avaliacao\ConfiguracaoAvaliacaoController;
use App\Http\Controllers\Avaliacao\EscalaController;
use App\Http\Controllers\Avaliacao\LancamentoController;
use App\Http\Controllers\Avaliacao\PautaController;
use App\Http\Controllers\Avaliacao\PeriodoController;
use Illuminate\Support\Facades\Route;

Route::prefix('avaliacao')->name('avaliacao.')->group(function () {
    /*
    | Configuração de Avaliações (global, versionada)
    */
    Route::middleware('permissao:avaliacao.configurar')->group(function () {
        Route::get('/configuracoes', [ConfiguracaoAvaliacaoController::class, 'index'])->name('configuracoes.index');
        Route::get('/configuracoes/criar', [ConfiguracaoAvaliacaoController::class, 'create'])->name('configuracoes.create');
        Route::post('/configuracoes', [ConfiguracaoAvaliacaoController::class, 'store'])->name('configuracoes.store');
        Route::post('/configuracoes/pre-visualizar', [ConfiguracaoAvaliacaoController::class, 'previsualizar'])->name('configuracoes.previsualizar');
        Route::get('/configuracoes/{configuracao}', [ConfiguracaoAvaliacaoController::class, 'show'])->name('configuracoes.show');
        Route::get('/configuracoes/{configuracao}/editar', [ConfiguracaoAvaliacaoController::class, 'edit'])->name('configuracoes.edit');
        Route::put('/configuracoes/{configuracao}', [ConfiguracaoAvaliacaoController::class, 'update'])->name('configuracoes.update');
        Route::post('/configuracoes/{configuracao}/duplicar', [ConfiguracaoAvaliacaoController::class, 'duplicar'])->name('configuracoes.duplicar');
        Route::patch('/configuracoes/{configuracao}/estado', [ConfiguracaoAvaliacaoController::class, 'alterarEstado'])->name('configuracoes.estado');
        Route::get('/configuracoes/{configuracao}/versoes/{versao}', [ConfiguracaoAvaliacaoController::class, 'versao'])->whereNumber('versao')->name('configuracoes.versao');

        Route::get('/escalas', [EscalaController::class, 'index'])->name('escalas.index');
        Route::put('/escalas/{escala}', [EscalaController::class, 'update'])->name('escalas.update');
        Route::post('/modelos', [EscalaController::class, 'guardarModelo'])->name('modelos.store');
        Route::put('/modelos/{modelo}', [EscalaController::class, 'guardarModelo'])->name('modelos.update');
    });

    /*
    | Operação na escola activa
    */
    Route::middleware('escola.activa')->group(function () {
        Route::middleware('permissao:notas.lancar|notas.ver')->group(function () {
            Route::get('/lancamento', [LancamentoController::class, 'index'])->name('lancamento.index');
            Route::get('/lancamento/{turma}/{disciplina}', [LancamentoController::class, 'grelha'])->whereNumber('disciplina')->name('lancamento.grelha');
        });
        Route::middleware('permissao:notas.lancar')->group(function () {
            Route::post('/lancamento/{turma}/{disciplina}', [LancamentoController::class, 'guardar'])->whereNumber('disciplina')->name('lancamento.guardar');
            Route::post('/lancamento/{turma}/{disciplina}/colunas', [LancamentoController::class, 'adicionarColuna'])->whereNumber('disciplina')->name('lancamento.coluna');
        });

        Route::middleware('permissao:pautas.fechar')->group(function () {
            Route::get('/periodos', [PeriodoController::class, 'index'])->name('periodos.index');
            Route::post('/periodos/fechar', [PeriodoController::class, 'fechar'])->name('periodos.fechar');
            Route::post('/periodos/reabrir', [PeriodoController::class, 'reabrir'])->name('periodos.reabrir');
            Route::post('/turmas/{turma}/recalcular', [PeriodoController::class, 'recalcular'])->name('periodos.recalcular');
        });

        Route::middleware('permissao:pautas.ver')->group(function () {
            Route::get('/pautas', [PautaController::class, 'index'])->name('pautas.index');
            Route::get('/pautas/turma/{turma}', [PautaController::class, 'turma'])->name('pautas.turma');
            Route::get('/pautas/turma/{turma}/disciplina/{disciplina}', [PautaController::class, 'disciplina'])->whereNumber('disciplina')->name('pautas.disciplina');
            Route::get('/pautas/classe/{classe}', [PautaController::class, 'classe'])->name('pautas.classe');
        });
    });
});
