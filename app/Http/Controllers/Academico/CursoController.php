<?php

namespace App\Http\Controllers\Academico;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\NivelEnsino;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CursoController extends Controller
{
    use AutorizaConfiguracao;

    public function index(Request $request, ContextoEscola $ctx): View
    {
        $cursos = Curso::withoutGlobalScope('escola')->daEscolaOuGlobal($ctx->escolaActivaId())
            ->with('nivelEnsino', 'escola')
            ->when($request->filled('nivel_id'), fn ($q) => $q->where('nivel_ensino_id', $request->integer('nivel_id')))
            ->orderByRaw('escola_id IS NOT NULL')->orderBy('nome')->get();

        return view('academico.cursos.index', [
            'cursos' => $cursos,
            'niveis' => NivelEnsino::ordenados()->get(),
            'escolaActiva' => $ctx->escolaActiva(),
            'controlador' => $this,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $escolaId = $this->escolaDestino($request);
        $dados = $this->validar($request, $escolaId);
        $curso = new Curso($dados + ['activo' => true]);
        $curso->escola_id = $escolaId;
        $curso->save();

        return back()->with('sucesso', 'Curso registado'.($escolaId ? ' para a escola.' : ' como referência provincial.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $curso = $this->encontrar($id);
        $this->exigirEdicao($curso->escola_id);
        $curso->update($this->validar($request, $curso->escola_id, $curso));

        return back()->with('sucesso', 'Curso actualizado.');
    }

    public function alternarEstado(int $id): RedirectResponse
    {
        $curso = $this->encontrar($id);
        $this->exigirEdicao($curso->escola_id);
        $curso->update(['activo' => ! $curso->activo]);

        return back()->with('sucesso', $curso->nome.' '.($curso->activo ? 'activado' : 'desactivado').'.');
    }

    /** Apenas cursos de referência ou da escola activa (isolamento). */
    private function encontrar(int $id): Curso
    {
        return Curso::withoutGlobalScope('escola')->daEscolaOuGlobal(app(ContextoEscola::class)->escolaActivaId())->findOrFail($id);
    }

    private function validar(Request $request, ?int $escolaId, ?Curso $curso = null): array
    {
        $unico = fn (string $campo) => Rule::unique('cursos', $campo)->ignore($curso)
            ->where(fn ($q) => $escolaId ? $q->where('escola_id', $escolaId) : $q->whereNull('escola_id'));

        $dados = $request->validate([
            'nivel_ensino_id' => ['required', 'integer', Rule::exists('niveis_ensino', 'id')->where('exige_curso', true)],
            'nome' => ['required', 'string', 'max:150', $unico('nome')],
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', $unico('codigo')],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ], ['nivel_ensino_id.exists' => 'Seleccione um nível de ensino que exija curso.']);
        $dados['codigo'] = strtoupper($dados['codigo']);

        return $dados;
    }
}
