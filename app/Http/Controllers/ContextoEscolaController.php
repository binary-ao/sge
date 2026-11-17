<?php

namespace App\Http\Controllers;

use App\Models\Escola;
use App\Models\Municipio;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Escolha da escola activa. Cada utilizador só pode escolher escolas do seu âmbito
 * (validado no servidor por ContextoEscola).
 */
class ContextoEscolaController extends Controller
{
    public function escolher(Request $request, ContextoEscola $contexto): View
    {
        $escolas = Escola::visiveis()->activas()->with('municipio', 'tipologia')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.$request->q.'%')->orWhere('codigo', 'like', '%'.$request->q.'%')))
            ->when($request->filled('municipio_id'), fn ($q) => $q->where('municipio_id', $request->integer('municipio_id')))
            ->orderBy('nome')->paginate(15)->withQueryString();

        return view('contexto.escolher', [
            'escolas' => $escolas,
            'municipios' => Municipio::visiveis()->orderBy('nome')->get(),
            'escolaActivaId' => $contexto->escolaActivaId(),
            'podeSair' => $contexto->ambito() !== 'escola',
        ]);
    }

    public function definir(Request $request, ContextoEscola $contexto): RedirectResponse
    {
        $request->validate(['escola_id' => ['nullable', 'integer']]);
        $id = $request->filled('escola_id') ? $request->integer('escola_id') : null;

        if ($id === null && $contexto->ambito() === 'escola') {
            return back()->with('erro', 'O seu perfil está associado a uma escola.');
        }
        if ($id && Escola::whereKey($id)->where('activo', false)->exists() && $contexto->podeVerEscola($id)) {
            return back()->with('erro', 'Esta escola está desactivada no cadastro. Active-a em Cadastros e Configurações › Escolas para voltar a operar nela.');
        }
        if (! $contexto->definirEscolaActiva($id)) {
            abort(403, 'Não tem acesso a esta escola.');
        }

        if ($id) {
            $escola = Escola::find($id);
            Auditoria::registar('contexto', 'Entrou na área da escola: '.$escola->nome, $escola);

            return redirect()->route('escolas.show', $escola)->with('sucesso', 'Escola activa: '.$escola->nome);
        }

        return redirect()->route('inicio')->with('sucesso', 'Regressou à visão geral.');
    }
}
