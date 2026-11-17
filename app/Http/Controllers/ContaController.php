<?php

namespace App\Http\Controllers;

use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ContaController extends Controller
{
    public function edit(Request $request): View
    {
        return view('conta.edit', ['user' => $request->user()->load('perfil', 'municipio', 'escolas')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:30'],
        ]);
        $request->user()->update($dados);

        return back()->with('sucesso', 'Dados actualizados.');
    }

    public function senha(Request $request): RedirectResponse
    {
        $request->validate([
            'senha_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:senha_actual'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->password])->saveQuietly();
        Auditoria::registar('senha', 'Alterou a própria senha', $user);

        return back()->with('sucesso', 'Senha alterada com sucesso.');
    }
}
