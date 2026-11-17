<?php

namespace App\Http\Controllers\Avaliacao;

use App\Http\Controllers\Controller;
use App\Models\EscalaClassificacao;
use App\Models\HistoricoConfiguracao;
use App\Models\ModeloFormula;
use App\Services\Avaliacao\Formula\ErroFormula;
use App\Services\Avaliacao\Formula\MotorFormulas;
use App\Services\Avaliacao\GestorConfiguracoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Escalas de classificação e biblioteca de modelos de fórmula (ambas versionadas). */
class EscalaController extends Controller
{
    public function index(): View
    {
        return view('avaliacao.escalas.index', [
            'escalas' => EscalaClassificacao::with('intervalos')->withCount(['intervalos'])->orderBy('id')->get(),
            'modelos' => ModeloFormula::orderBy('referencia')->get(),
            'historico' => HistoricoConfiguracao::with('utilizador')->whereIn('entidade', ['escala', 'modelo_formula'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request, EscalaClassificacao $escala): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150', Rule::unique('escalas_classificacao', 'nome')->ignore($escala)],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'nota_minima' => ['required', 'numeric', 'min:0'],
            'nota_maxima' => ['required', 'numeric', 'gt:nota_minima', 'max:100'],
            'intervalos' => ['required', 'array', 'min:1', 'max:10'],
            'intervalos.*.minimo' => ['required', 'numeric'],
            'intervalos.*.maximo' => ['required', 'numeric'],
            'intervalos.*.rotulo' => ['required', 'string', 'max:60'],
            'intervalos.*.sigla' => ['nullable', 'string', 'max:10'],
            'intervalos.*.valor_referencia' => ['required', 'numeric'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['motivo.required' => 'Indique o motivo da alteração.']);

        // Intervalos dentro da escala, coerentes e sem sobreposição.
        $intervalos = collect($dados['intervalos'])->map(fn ($i) => array_map(fn ($v) => is_string($v) && is_numeric(str_replace(',', '.', $v)) ? (float) str_replace(',', '.', $v) : $v, $i))
            ->sortBy('minimo')->values();
        $erros = [];
        foreach ($intervalos as $k => $i) {
            if ($i['minimo'] > $i['maximo']) {
                $erros['intervalos'] = "No intervalo \"{$i['rotulo']}\" o mínimo é superior ao máximo.";
            }
            if ($i['minimo'] < $dados['nota_minima'] || $i['maximo'] > $dados['nota_maxima']) {
                $erros['intervalos'] = "O intervalo \"{$i['rotulo']}\" sai dos limites da escala.";
            }
            if ($i['valor_referencia'] < $i['minimo'] || $i['valor_referencia'] > $i['maximo']) {
                $erros['intervalos'] = "O valor de referência de \"{$i['rotulo']}\" tem de estar dentro do intervalo.";
            }
            if ($k > 0 && $i['minimo'] <= $intervalos[$k - 1]['maximo']) {
                $erros['intervalos'] = "Os intervalos \"{$intervalos[$k - 1]['rotulo']}\" e \"{$i['rotulo']}\" sobrepõem-se.";
            }
        }
        if ($erros) {
            throw ValidationException::withMessages($erros);
        }

        DB::transaction(function () use ($escala, $dados, $intervalos) {
            $escala->update([
                'nome' => $dados['nome'], 'descricao' => $dados['descricao'] ?? null,
                'nota_minima' => $dados['nota_minima'], 'nota_maxima' => $dados['nota_maxima'], 'versao' => $escala->versao + 1,
            ]);
            $escala->intervalos()->delete();
            foreach ($intervalos->sortByDesc('minimo')->values() as $n => $i) {
                $escala->intervalos()->create($i + ['ordem' => $n + 1]);
            }
            HistoricoConfiguracao::registar($escala->fresh('intervalos'), 'escala', 'alteracao', $dados['motivo']);
        });

        return back()->with('sucesso', "Escala actualizada (versão {$escala->versao}). Aplica-se aos próximos cálculos.");
    }

    public function guardarModelo(Request $request, GestorConfiguracoes $gestor, ?ModeloFormula $modelo = null): RedirectResponse
    {
        $modelo = $modelo?->exists ? $modelo : new ModeloFormula;
        $dados = $request->validate([
            'referencia' => ['required', 'string', 'max:20', Rule::unique('modelos_formula', 'referencia')->ignore($modelo)],
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'nome' => ['required', 'string', 'max:150'],
            'expressao' => ['required', 'string', 'max:500'],
            'nivel' => ['required', Rule::in(['trimestral', 'anual'])],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'aplicacao' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'motivo' => [$modelo->exists ? 'required' : 'nullable', 'string', 'min:5', 'max:1000'],
        ]);
        try {
            MotorFormulas::compilar($dados['expressao']);
        } catch (ErroFormula $e) {
            throw ValidationException::withMessages(['expressao' => $e->getMessage()]);
        }
        $dados['codigo'] = strtoupper($dados['codigo']);
        $dados['activo'] = $request->boolean('activo');
        $gestor->guardarModelo($modelo, collect($dados)->except('motivo')->all(), $dados['motivo'] ?? 'Criação do modelo.');

        return back()->with('sucesso', 'Modelo de fórmula guardado (versão '.$modelo->versao.'). As configurações existentes não são alteradas.');
    }
}
