<?php

use App\Http\Controllers\Admin\LogActividadeController;
use App\Http\Controllers\Admin\PerfilController;
use App\Http\Controllers\Admin\UtilizadorController;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\Auth\RecuperacaoSenhaController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\ContextoEscolaController;
use App\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

/*
| Autenticação
*/
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AutenticacaoController::class, 'create'])->name('login');
    Route::post('/entrar', [AutenticacaoController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/recuperar-senha', [RecuperacaoSenhaController::class, 'pedido'])->name('password.request');
    Route::post('/recuperar-senha', [RecuperacaoSenhaController::class, 'enviarLigacao'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/redefinir-senha/{token}', [RecuperacaoSenhaController::class, 'formulario'])->name('password.reset');
    Route::post('/redefinir-senha', [RecuperacaoSenhaController::class, 'redefinir'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/sair', [AutenticacaoController::class, 'destroy'])->middleware('auth')->name('logout');

/*
| Área autenticada
*/
Route::middleware(['auth', 'activo'])->group(function () {
    Route::get('/', InicioController::class)->name('inicio');
    Route::get('/em-desenvolvimento/{modulo}', [InicioController::class, 'emDesenvolvimento'])->name('em-desenvolvimento');

    // Conta pessoal
    Route::get('/minha-conta', [ContaController::class, 'edit'])->name('conta.edit');
    Route::put('/minha-conta', [ContaController::class, 'update'])->name('conta.update');
    Route::put('/minha-conta/senha', [ContaController::class, 'senha'])->name('conta.senha');

    // Escola activa (contexto)
    Route::get('/contexto/escola', [ContextoEscolaController::class, 'escolher'])->name('contexto.escolher');
    Route::post('/contexto/escola', [ContextoEscolaController::class, 'definir'])->name('contexto.definir');

    /*
    | Cadastros e Configurações: municípios, escolas, tipologias, disciplinas,
    | níveis de ensino, classes e ciclos, anos lectivos e períodos de avaliação.
    */
    require __DIR__.'/cadastros.php';

    /*
    | Administração do sistema (Fase 1)
    */
    Route::middleware('permissao:utilizadores.gerir')->group(function () {
        Route::get('/utilizadores', [UtilizadorController::class, 'index'])->name('utilizadores.index');
        Route::get('/utilizadores/criar', [UtilizadorController::class, 'create'])->name('utilizadores.create');
        Route::post('/utilizadores', [UtilizadorController::class, 'store'])->name('utilizadores.store');
        Route::get('/utilizadores/{utilizador}/editar', [UtilizadorController::class, 'edit'])->name('utilizadores.edit');
        Route::put('/utilizadores/{utilizador}', [UtilizadorController::class, 'update'])->name('utilizadores.update');
        Route::patch('/utilizadores/{utilizador}/estado', [UtilizadorController::class, 'alternarEstado'])->name('utilizadores.estado');
    });

    Route::middleware('permissao:perfis.gerir')->group(function () {
        Route::get('/perfis', [PerfilController::class, 'index'])->name('perfis.index');
        Route::get('/perfis/{perfil}/permissoes', [PerfilController::class, 'edit'])->name('perfis.edit');
        Route::put('/perfis/{perfil}/permissoes', [PerfilController::class, 'update'])->name('perfis.update');
    });

    Route::middleware('permissao:sistema.backups')->group(function () {
        Route::get('/sistema/copias', [\App\Http\Controllers\Admin\CopiaSegurancaController::class, 'index'])->name('copias.index');
        Route::post('/sistema/copias', [\App\Http\Controllers\Admin\CopiaSegurancaController::class, 'criar'])->middleware('throttle:3,1')->name('copias.criar');
        Route::get('/sistema/copias/{ficheiro}', [\App\Http\Controllers\Admin\CopiaSegurancaController::class, 'descarregar'])->name('copias.descarregar');
    });

    Route::middleware('permissao:logs.ver')->group(function () {
        Route::get('/historico', [LogActividadeController::class, 'index'])->name('logs.index');
        Route::get('/historico/{log}', [LogActividadeController::class, 'show'])->name('logs.show');
    });

    /*
    | Gestão académica (Fase 3)
    */
    require __DIR__.'/academico.php';

    /*
    | Avaliação das aprendizagens (Fase 4)
    */
    require __DIR__.'/avaliacao.php';

    /*
    | Painéis, mapa e relatórios (Fase 5)
    */
    require __DIR__.'/paineis.php';

    /*
    | Faltas, pagamentos e declarações (Fase 6)
    */
    require __DIR__.'/administrativo.php';
});
