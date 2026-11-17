<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ServicoPagavel;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Serviços pagáveis e respectivos preços (variáveis por escola e classe). */
class ServicoPagavelController extends Controller
{
    public function index(ContextoEscola $ctx): View
    {
        return view('administrativo.servicos.index', [
            'servicos' => ServicoPagavel::with('classe.nivelEnsino')->withCount('pagamentos')->orderBy('tipo')->orderBy('nome')->get(),
            'classes' => Classe::with('nivelEnsino')->daEscola($ctx->escolaActiva())->ordenadas()->get(),
        ]);
    }

    public function store(Request $request, ContextoEscola $ctx): RedirectResponse
    {
        ServicoPagavel::create($this->validar($request, $ctx) + ['activo' => true]);

        return back()->with('sucesso', 'Serviço registado.');
    }

    public function update(Request $request, ServicoPagavel $servico, ContextoEscola $ctx): RedirectResponse
    {
        $dados = $this->validar($request, $ctx, $servico);
        if ($servico->pagamentos()->exists() && ($dados['tipo'] !== $servico->tipo)) {
            return back()->with('erro', 'Não é possível alterar o tipo de um serviço com pagamentos registados. Crie um novo serviço.');
        }
        $servico->update($dados);

        return back()->with('sucesso', 'Serviço actualizado. O novo preço aplica-se aos pagamentos seguintes; os recibos emitidos mantêm o valor cobrado.');
    }

    public function alternarEstado(ServicoPagavel $servico): RedirectResponse
    {
        $servico->update(['activo' => ! $servico->activo]);

        return back()->with('sucesso', $servico->nome.' '.($servico->activo ? 'activado' : 'desactivado').'.');
    }

    private function validar(Request $request, ContextoEscola $ctx, ?ServicoPagavel $servico = null): array
    {
        $classes = Classe::daEscola($ctx->escolaActiva())->pluck('classes.id')->all();
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(config('administrativo.pagamentos.tipos_servico')))],
            'nome' => ['required', 'string', 'max:150'],
            'classe_id' => ['nullable', 'integer', Rule::in($classes)],
            'valor' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ], [], ['classe_id' => 'classe', 'valor' => 'valor']);
        $dados['classe_id'] = $dados['classe_id'] ?? null;

        // Uma única mensalidade activa por classe (ou geral).
        if ($dados['tipo'] === 'mensalidade') {
            $duplicada = ServicoPagavel::where('tipo', 'mensalidade')->where('activo', true)
                ->where(fn ($q) => $dados['classe_id'] ? $q->where('classe_id', $dados['classe_id']) : $q->whereNull('classe_id'))
                ->when($servico, fn ($q) => $q->whereKeyNot($servico->id))->exists();
            if ($duplicada) {
                throw \Illuminate\Validation\ValidationException::withMessages(['classe_id' => 'Já existe uma mensalidade activa para esta classe. Edite-a ou desactive-a.']);
            }
        }

        return $dados;
    }
}
