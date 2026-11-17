<?php

namespace App\Http\Controllers\Rede;

use App\Http\Controllers\Controller;
use App\Models\Escola;
use App\Models\Municipio;
use App\Models\Provincia;
use App\Support\Auditoria;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MunicipioController extends Controller
{
    public function index(Request $request): View
    {
        $municipios = Municipio::visiveis()->with('provincia')
            ->withCount([
                'escolas',
                'escolas as escolas_activas' => fn ($q) => $q->where('activo', true),
                'alunos as total_alunos' => fn ($q) => $q->where('alunos.estado', 'activo')->where('escolas.activo', true),
            ])
            ->when($request->filled('provincia_id'), fn ($q) => $q->where('provincia_id', $request->integer('provincia_id')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.$request->q.'%')
                ->orWhere('codigo', 'like', '%'.$request->q.'%')
                ->orWhere('sede', 'like', '%'.$request->q.'%')))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activo'))
            ->orderBy('nome')->paginate(20)->withQueryString();

        return view('rede.municipios.index', [
            'municipios' => $municipios,
            'provincias' => Provincia::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function show(Municipio $municipio, ContextoEscola $contexto): View
    {
        abort_unless(Municipio::visiveis()->whereKey($municipio->id)->exists(), 404);

        $escolas = Escola::visiveis()->where('municipio_id', $municipio->id)
            ->with('tipologia', 'niveisEnsino')->comIndicadores(\App\Models\AnoLectivo::actual()?->id)->orderBy('nome')->get();
        $activas = $escolas->where('activo', true);

        return view('rede.municipios.show', [
            'municipio' => $municipio->load('provincia'),
            'escolas' => $escolas,
            'indicadores' => [
                'escolas' => $activas->count(),
                'inactivas' => $escolas->count() - $activas->count(),
                'alunos' => $activas->sum('total_alunos'),
                'professores' => $activas->sum('total_professores'),
                'turmas' => $activas->sum('total_turmas'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('rede.municipios.form', [
            'municipio' => new Municipio(['activo' => true, 'provincia_id' => Provincia::where('codigo', 'BGO')->value('id')]),
            'provincias' => Provincia::orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $municipio = Municipio::create($this->validar($request));

        return redirect()->route('municipios.show', $municipio)->with('sucesso', 'Município registado com sucesso.');
    }

    public function edit(Municipio $municipio): View
    {
        return view('rede.municipios.form', ['municipio' => $municipio, 'provincias' => Provincia::orderBy('nome')->get()]);
    }

    public function update(Request $request, Municipio $municipio): RedirectResponse
    {
        $municipio->update($this->validar($request, $municipio));

        return redirect()->route('municipios.show', $municipio)->with('sucesso', 'Município actualizado.');
    }

    /** Activar/desactivar (não se eliminam municípios, para preservar o histórico). */
    public function alternarEstado(Municipio $municipio): RedirectResponse
    {
        // Integridade: um município desactivado não pode ter escolas activas.
        if ($municipio->activo && ($activas = $municipio->escolas()->where('activo', true)->count())) {
            return back()->with('erro', "Não é possível desactivar {$municipio->nome}: tem {$activas} escola(s) activa(s). Desactive-as ou associe-as a outro município primeiro.");
        }
        $municipio->update(['activo' => ! $municipio->activo]);
        Auditoria::registar($municipio->activo ? 'activar' : 'desactivar',
            ($municipio->activo ? 'Activou' : 'Desactivou').' o município '.$municipio->nome, $municipio);

        return back()->with('sucesso', 'Município '.($municipio->activo ? 'activado' : 'desactivado').'.');
    }

    private function validar(Request $request, ?Municipio $municipio = null): array
    {
        $dados = $request->validate([
            'provincia_id' => ['required', 'integer', 'exists:provincias,id'],
            'nome' => ['required', 'string', 'max:100', Rule::unique('municipios', 'nome')->where('provincia_id', $request->integer('provincia_id'))->ignore($municipio)],
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('municipios', 'codigo')->ignore($municipio)],
            'sede' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nome.unique' => 'Já existe um município com este nome nesta província.',
            'codigo.unique' => 'Já existe um município com este código.',
        ]);
        $dados['codigo'] = strtoupper($dados['codigo']);
        $dados['activo'] = $request->boolean('activo');
        if ($municipio?->activo && ! $dados['activo'] && $municipio->escolas()->where('activo', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['activo' => 'O município tem escolas activas e não pode ser desactivado.']);
        }

        return $dados;
    }
}
