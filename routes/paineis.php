<?php

/*
| Fase 5 — Painéis, mapa da rede escolar e relatórios.
| Incluído em routes/web.php, dentro do grupo ['auth', 'activo'].
*/

use App\Http\Controllers\Paineis\MapaController;
use App\Http\Controllers\Paineis\PainelController;
use App\Http\Controllers\Paineis\RelatorioController;
use Illuminate\Support\Facades\Route;

Route::prefix('paineis')->name('paineis.')->group(function () {
    Route::get('/provincial', [PainelController::class, 'provincial'])->middleware('permissao:painel.provincial')->name('provincial');
    Route::get('/municipal', [PainelController::class, 'municipal'])->middleware('permissao:painel.municipal')->name('municipal');
    Route::get('/escola', [PainelController::class, 'escola'])->middleware(['permissao:painel.escola', 'escola.activa'])->name('escola');
});

Route::get('/mapa', [MapaController::class, 'index'])->middleware('permissao:mapa.ver')->name('mapa');

Route::middleware('permissao:relatorios.provinciais|relatorios.municipais|relatorios.escolares')->group(function () {
    Route::get('/relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
    Route::get('/relatorios/{chave}', [RelatorioController::class, 'show'])->where('chave', '[a-z\-]+')->name('relatorios.show');
});
