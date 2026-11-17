@extends('layouts.auth')
@section('titulo', 'Redefinir senha')
@section('conteudo')
    <h2>Definir nova senha</h2>
    <p class="text-secondary small mb-4">A nova senha deve ter pelo menos 8 caracteres, com letras e números.</p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" required>
            <x-campo-erro campo="email" />
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Nova senha</label>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
            <x-campo-erro campo="password" />
        </div>
        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">Guardar nova senha</button>
    </form>
@endsection
