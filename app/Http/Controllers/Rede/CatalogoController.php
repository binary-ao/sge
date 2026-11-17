<?php

namespace App\Http\Controllers\Rede;

use App\Http\Controllers\Controller;
use App\Models\TipologiaEscola;
use App\Support\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de tipologias institucionais. Os níveis de ensino têm gestão própria
 * (Cadastros\NivelEnsinoController): tipologia e nível são conceitos independentes.
 */
class CatalogoController extends Controller
{
    private const CATALOGOS = [
        'tipologias' => [
            'modelo' => TipologiaEscola::class,
            'tabela' => 'tipologias_escola',
            'titulo' => 'Tipologias institucionais',
            'singular' => 'tipologia',
            'campo_codigo' => 'sigla',
            'rotulo_codigo' => 'Sigla',
            'relacao_contagem' => 'escolas',
            'descricao' => 'Classificação da instituição pela sua natureza organizativa (ex.: Complexo Escolar, Liceu). Esta lista é uma organização inicial da plataforma, não uma enumeração legal exaustiva.',
        ],
    ];

    public function index(Request $request): View
    {
        $tipo = $this->tipo($request);
        $cfg = self::CATALOGOS[$tipo];

        $itens = $cfg['modelo']::query()
            ->withCount($cfg['relacao_contagem'])
            ->when($request->filled('q'), fn ($q) => $q->where('nome', 'like', '%'.$request->q.'%'))
            ->orderBy('ordem')->orderBy('nome')->get();

        return view('rede.catalogos.index', compact('tipo', 'cfg', 'itens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tipo = $this->tipo($request);
        $cfg = self::CATALOGOS[$tipo];
        $cfg['modelo']::create($this->validar($request, $cfg));

        return back()->with('sucesso', ucfirst($cfg['singular']).' registada com sucesso.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tipo = $this->tipo($request);
        $cfg = self::CATALOGOS[$tipo];
        $item = $cfg['modelo']::findOrFail($id);
        $item->update($this->validar($request, $cfg, $item));

        return back()->with('sucesso', ucfirst($cfg['singular']).' actualizada.');
    }

    public function alternarEstado(Request $request, int $id): RedirectResponse
    {
        $tipo = $this->tipo($request);
        $cfg = self::CATALOGOS[$tipo];
        $item = $cfg['modelo']::findOrFail($id);
        $item->update(['activo' => ! $item->activo]);
        Auditoria::registar($item->activo ? 'activar' : 'desactivar',
            ($item->activo ? 'Activou' : 'Desactivou')." {$cfg['singular']}: {$item->nome}", $item);

        return back()->with('sucesso', '"'.$item->nome.'" '.($item->activo ? 'activado' : 'desactivado').'.');
    }

    private function tipo(Request $request): string
    {
        $tipo = $request->route()->defaults['catalogo'] ?? null;
        abort_unless(isset(self::CATALOGOS[$tipo]), 404);

        return $tipo;
    }

    private function validar(Request $request, array $cfg, ?Model $item = null): array
    {
        $codigo = $cfg['campo_codigo'];
        $regraCodigo = ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-_]+$/'];
        if ($codigo === 'codigo') {
            $regraCodigo[0] = 'required';
            $regraCodigo[] = Rule::unique($cfg['tabela'], 'codigo')->ignore($item);
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120', Rule::unique($cfg['tabela'], 'nome')->ignore($item)],
            $codigo => $regraCodigo,
            'descricao' => ['nullable', 'string', 'max:1000'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $dados['ordem'] = (int) ($dados['ordem'] ?? 0);
        if (! empty($dados[$codigo])) {
            $dados[$codigo] = strtoupper($dados[$codigo]);
        }
        if (! $item) {
            $dados['activo'] = true;
        }

        return $dados;
    }
}
