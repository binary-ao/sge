@extends('layouts.auth')
@section('titulo', 'Entrar')
@section('conteudo')
    <h2>Entrar</h2>
    <p class="text-secondary small mb-4">Utilize as credenciais atribuídas pela Direcção Provincial.</p>

    @if (session('status'))
        <div class="alert alert-success small py-2">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
            <x-campo-erro campo="email" />
        </div>
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label for="password" class="form-label">Senha</label>
                <a href="{{ route('password.request') }}" class="small">Esqueceu a senha?</a>
            </div>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
            <x-campo-erro campo="password" />
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="lembrar" id="lembrar" value="1">
            <label class="form-check-label small" for="lembrar">Manter sessão iniciada</label>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">Entrar</button>
    </form>
@endsection
