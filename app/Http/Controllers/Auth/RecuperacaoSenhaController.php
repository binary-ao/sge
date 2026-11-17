<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegraSenha;
use Illuminate\View\View;

class RecuperacaoSenhaController extends Controller
{
    public function pedido(): View
    {
        return view('auth.esqueci-senha');
    }

    public function enviarLigacao(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:150']]);

        // Só contas activas recebem ligação. A resposta é sempre a mesma,
        // para não revelar que e-mails existem no sistema.
        $user = User::where('email', $request->email)->where('activo', true)->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', __('passwords.sent'));
    }

    public function formulario(Request $request, string $token): View
    {
        return view('auth.redefinir-senha', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function redefinir(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegraSenha::min(8)->letters()->numbers()],
        ]);

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->saveQuietly();
                event(new PasswordReset($user));
            }
        );

        return $estado === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($estado))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($estado)]);
    }
}
