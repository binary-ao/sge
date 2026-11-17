<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ContextoEscola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutenticacaoController extends Controller
{
    private const MAX_TENTATIVAS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $chave = Str::lower($credenciais['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($chave, self::MAX_TENTATIVAS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($chave)]),
            ]);
        }

        // Apenas contas activas podem entrar.
        if (! Auth::attempt($credenciais + ['activo' => true], $request->boolean('lembrar'))) {
            RateLimiter::hit($chave, 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();
        $request->session()->forget(ContextoEscola::SESSAO);

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
