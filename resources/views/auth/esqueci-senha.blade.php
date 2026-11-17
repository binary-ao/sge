@extends('layouts.auth')
@section('titulo', 'Recuperar senha')
@section('conteudo')
    <h2>Recuperar senha</h2>
    <p class="text-secondary small mb-4">Indique o e-mail da sua conta. Enviaremos uma ligação para definir uma nova senha.</p>

    @if (session('status'))
        <div class="alert alert-success small py-2">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-4">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
            <x-campo-erro campo="email" />
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">Enviar ligação</button>
        <div class="text-center mt-3"><a href="{{ route('login') }}" class="small"><i class="bi bi-arrow-left"></i> Voltar ao início de sessão</a></div>
    </form>
@endsection
