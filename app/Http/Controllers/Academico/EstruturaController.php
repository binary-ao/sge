<?php

namespace App\Http\Controllers\Academico;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Turnos (referência provincial). Classes e ciclos são geridos em Cadastros e Configurações.
 */
class EstruturaController extends Controller
{
    public function index(): View
    {
        return view('academico.estrutura.index', ['turnos' => Turno::orderBy('ordem')->get()]);
    }

    public function guardarTurno(Request $request, ?Turno $turno = null): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:40', Rule::unique('turnos', 'nome')->ignore($turno)],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);
        $dados['ordem'] = (int) ($dados['ordem'] ?? 0);
        $turno?->exists ? $turno->update($dados) : Turno::create($dados + ['activo' => true]);

        return back()->with('sucesso', 'Turno guardado.');
    }

    public function alternarTurno(Turno $turno): RedirectResponse
    {
        $turno->update(['activo' => ! $turno->activo]);

        return back()->with('sucesso', 'Turno '.$turno->nome.' '.($turno->activo ? 'activado' : 'desactivado').'.');
    }
}
