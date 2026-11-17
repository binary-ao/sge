<x-app-layout titulo="A minha conta">
    <x-cabecalho titulo="A minha conta" :migalhas="['A minha conta' => null]" />

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-2" style="width:64px;height:64px;font-size:1.3rem">{{ $user->iniciais }}</div>
                    <div class="fw-semibold">{{ $user->nome }}</div>
                    <div class="small text-secondary">{{ $user->email }}</div>
                    <span class="badge badge-suave mt-2">{{ $user->perfil->nome }}</span>
                </div>
                <div class="card-body border-top">
                    <dl class="lista-detalhes">
                        <dt>Âmbito</dt><dd>{{ $user->perfil->ambito_label }}</dd>
                        <dt>Atribuição</dt><dd>{{ $user->ambito_descricao }}</dd>
                        <dt>Último acesso</dt><dd>{{ $user->ultimo_acesso_em?->format('d/m/Y H:i') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h2 class="titulo">Dados pessoais</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('conta.update') }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label obrigatorio" for="nome">Nome</label>
                                <input id="nome" name="nome" value="{{ old('nome', $user->nome) }}" class="form-control @error('nome') is-invalid @enderror" required maxlength="150">
                                <x-campo-erro campo="nome" />
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="telefone">Telefone</label>
                                <input id="telefone" name="telefone" value="{{ old('telefone', $user->telefone) }}" class="form-control @error('telefone') is-invalid @enderror" maxlength="30">
                                <x-campo-erro campo="telefone" />
                            </div>
                        </div>
                        <div class="text-end mt-3"><button class="btn btn-primary">Guardar</button></div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="titulo">Alterar senha</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('conta.senha') }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label obrigatorio" for="senha_actual">Senha actual</label>
                                <input type="password" id="senha_actual" name="senha_actual" class="form-control @error('senha_actual') is-invalid @enderror" required autocomplete="current-password">
                                <x-campo-erro campo="senha_actual" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label obrigatorio" for="password">Nova senha</label>
                                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                                <x-campo-erro campo="password" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label obrigatorio" for="password_confirmation">Confirmar</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                            </div>
                        </div>
                        <div class="form-text">Mínimo de 8 caracteres, com letras e números.</div>
                        <div class="text-end mt-3"><button class="btn btn-primary">Alterar senha</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
