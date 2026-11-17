<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function index(): View
    {
        return view('admin.perfis.index', [
            'perfis' => Perfil::withCount(['utilizadores', 'permissoes'])->orderBy('id')->get(),
            'totalPermissoes' => Permissao::count(),
        ]);
    }

    public function edit(Perfil $perfil): View
    {
        return view('admin.perfis.edit', [
            'perfil' => $perfil->load('permissoes'),
            'modulos' => Permissao::orderBy('id')->get()->groupBy('modulo'),
            'seleccionadas' => $perfil->permissoes->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Perfil $perfil): RedirectResponse
    {
        if ($perfil->isSuperutilizador()) {
            return back()->with('erro', 'As permissões do Superutilizador são globais e não podem ser alteradas.');
        }

        $dados = $request->validate([
            'permissoes' => ['nullable', 'array'],
            'permissoes.*' => ['integer', 'exists:permissoes,id'],
        ]);

        $antes = $perfil->permissoes()->pluck('codigo')->all();
        $perfil->permissoes()->sync($dados['permissoes'] ?? []);
        $depois = $perfil->permissoes()->pluck('codigo')->all();

        Auditoria::registar('permissoes', 'Alterou as permissões do perfil '.$perfil->nome, $perfil, [
            'adicionadas' => array_values(array_diff($depois, $antes)),
            'removidas' => array_values(array_diff($antes, $depois)),
        ]);

        return redirect()->route('perfis.index')->with('sucesso', 'Permissões do perfil "'.$perfil->nome.'" actualizadas.');
    }
}
