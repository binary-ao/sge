<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Escola;
use App\Models\Municipio;
use App\Models\Perfil;
use App\Models\User;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class UtilizadorController extends Controller
{
    public function index(Request $request): View
    {
        $utilizadores = User::with('perfil', 'municipio', 'escolas')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')))
            ->when($request->filled('perfil_id'), fn ($q) => $q->where('perfil_id', $request->integer('perfil_id')))
            ->when($request->filled('escola_id'), fn ($q) => $q->whereHas('escolas', fn ($e) => $e->where('escolas.id', $request->integer('escola_id'))))
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activo'))
            ->orderBy('nome')->paginate(20)->withQueryString();

        return view('admin.utilizadores.index', [
            'utilizadores' => $utilizadores,
            'perfis' => Perfil::orderBy('id')->get(),
            'escolas' => Escola::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create(): View
    {
        return view('admin.utilizadores.form', $this->dadosFormulario(new User(['activo' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $this->validar($request);

        $user = DB::transaction(function () use ($dados) {
            $user = User::create($dados['user']);
            $user->escolas()->sync($dados['escolas']);

            return $user;
        });

        return redirect()->route('utilizadores.index')->with('sucesso', "Utilizador {$user->nome} criado com sucesso.");
    }

    public function edit(User $utilizador): View
    {
        return view('admin.utilizadores.form', $this->dadosFormulario($utilizador->load('escolas')));
    }

    public function update(Request $request, User $utilizador): RedirectResponse
    {
        $dados = $this->validar($request, $utilizador);

        if ($utilizador->is($request->user()) && (! $dados['user']['activo'] || $dados['user']['perfil_id'] !== $utilizador->perfil_id)) {
            return back()->withInput()->with('erro', 'Não pode desactivar nem alterar o perfil da sua própria conta.');
        }

        DB::transaction(function () use ($dados, $utilizador) {
            $utilizador->update($dados['user']);
            $alteracoes = $utilizador->escolas()->sync($dados['escolas']);
            if (array_filter($alteracoes)) {
                Auditoria::registar('actualizar', 'Alterou as escolas atribuídas a '.$utilizador->nome, $utilizador,
                    ['escolas' => Escola::whereIn('id', $dados['escolas'])->pluck('nome')->all()]);
            }
        });

        return redirect()->route('utilizadores.index')->with('sucesso', 'Utilizador actualizado.');
    }

    public function alternarEstado(Request $request, User $utilizador): RedirectResponse
    {
        if ($utilizador->is($request->user())) {
            return back()->with('erro', 'Não pode desactivar a sua própria conta.');
        }
        $utilizador->update(['activo' => ! $utilizador->activo]);
        if (! $utilizador->activo) {
            // Termina as sessões abertas do utilizador desactivado.
            DB::table('sessions')->where('user_id', $utilizador->id)->delete();
        }

        return back()->with('sucesso', $utilizador->nome.' '.($utilizador->activo ? 'activado' : 'desactivado').'.');
    }

    private function dadosFormulario(User $user): array
    {
        return [
            'utilizador' => $user,
            'perfis' => Perfil::orderBy('id')->get(),
            'municipios' => Municipio::orderBy('nome')->get(),
            'escolas' => Escola::with('municipio')->orderBy('nome')->get(),
            'escolasSeleccionadas' => collect(old('escolas', $user->exists ? $user->escolas->pluck('id')->all() : []))
                ->map(fn ($i) => (int) $i)->all(),
        ];
    }

    /**
     * Valida os dados e garante a coerência entre o perfil e o âmbito:
     * Direcção Municipal exige município; perfis de escola exigem pelo menos uma escola.
     */
    private function validar(Request $request, ?User $user = null): array
    {
        $perfil = Perfil::find($request->integer('perfil_id'));

        $validado = validator($request->all(), [
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('utilizadores', 'email')->ignore($user)],
            'telefone' => ['nullable', 'string', 'max:30'],
            'perfil_id' => ['required', 'integer', 'exists:perfis,id'],
            'municipio_id' => ['nullable', 'integer', 'exists:municipios,id'],
            'escolas' => ['nullable', 'array'],
            'escolas.*' => ['integer', 'distinct', 'exists:escolas,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'activo' => ['nullable', 'boolean'],
        ])->after(function (Validator $v) use ($request, $perfil) {
            if ($perfil?->ambito === 'municipio' && ! $request->filled('municipio_id')) {
                $v->errors()->add('municipio_id', 'Seleccione o município da Direcção Municipal.');
            }
            if ($perfil?->ambito === 'escola' && empty($request->input('escolas'))) {
                $v->errors()->add('escolas', 'Seleccione pelo menos uma escola para este perfil.');
            }
        })->validate();

        $dadosUser = [
            'nome' => $validado['nome'],
            'email' => strtolower($validado['email']),
            'telefone' => $validado['telefone'] ?? null,
            'perfil_id' => (int) $validado['perfil_id'],
            // O município só se aplica ao âmbito municipal; as escolas só ao âmbito de escola.
            'municipio_id' => $perfil->ambito === 'municipio' ? (int) $validado['municipio_id'] : null,
            'activo' => $request->boolean('activo'),
        ];
        if (! empty($validado['password'])) {
            $dadosUser['password'] = $validado['password'];
        }

        return [
            'user' => $dadosUser,
            'escolas' => $perfil->ambito === 'escola' ? array_map('intval', $validado['escolas'] ?? []) : [],
        ];
    }
}
