@php $editar = $utilizador->exists; @endphp
<x-app-layout :titulo="$editar ? 'Editar utilizador' : 'Novo utilizador'">
    <x-cabecalho :titulo="$editar ? 'Editar utilizador' : 'Novo utilizador'"
        :migalhas="['Sistema' => null, 'Utilizadores' => route('utilizadores.index'), ($editar ? $utilizador->nome : 'Novo') => null]" />

    <form method="POST" action="{{ $editar ? route('utilizadores.update', $utilizador) : route('utilizadores.store') }}" novalidate>
        @csrf
        @if ($editar) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="seccao-form">Dados da conta</div>
                        <div class="mb-3">
                            <label class="form-label obrigatorio" for="nome">Nome completo</label>
                            <input id="nome" name="nome" value="{{ old('nome', $utilizador->nome) }}" maxlength="150" class="form-control @error('nome') is-invalid @enderror" required>
                            <x-campo-erro campo="nome" />
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label obrigatorio" for="email">E-mail</label>
                                <input type="email" id="email" name="email" value="{{ old('email', $utilizador->email) }}" maxlength="150" class="form-control @error('email') is-invalid @enderror" required autocomplete="off">
                                <x-campo-erro campo="email" />
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="telefone">Telefone</label>
                                <input id="telefone" name="telefone" value="{{ old('telefone', $utilizador->telefone) }}" maxlength="30" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label {{ $editar ? '' : 'obrigatorio' }}" for="password">{{ $editar ? 'Nova senha' : 'Senha' }}</label>
                                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                                <x-campo-erro campo="password" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Confirmar senha</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                            <div class="col-12 form-text mt-1">Mínimo de 8 caracteres, com letras e números.{{ $editar ? ' Deixe em branco para manter a senha actual.' : '' }}</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked(old('activo', $utilizador->activo))>
                            <label class="form-check-label" for="activo">Conta activa</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="seccao-form">Perfil e âmbito de acesso</div>
                        <div class="mb-3">
                            <label class="form-label obrigatorio" for="perfil_id">Perfil</label>
                            <select id="perfil_id" name="perfil_id" class="form-select @error('perfil_id') is-invalid @enderror" required>
                                <option value="">Seleccione…</option>
                                @foreach ($perfis as $p)
                                    <option value="{{ $p->id }}" data-ambito="{{ $p->ambito }}" @selected(old('perfil_id', $utilizador->perfil_id) == $p->id)>{{ $p->nome }} — âmbito: {{ $p->ambito_label }}</option>
                                @endforeach
                            </select>
                            <x-campo-erro campo="perfil_id" />
                            <div class="form-text" id="descricaoAmbito"></div>
                        </div>

                        <div class="mb-3" data-ambito-bloco="municipio">
                            <label class="form-label obrigatorio" for="municipio_id">Município</label>
                            <select id="municipio_id" name="municipio_id" class="form-select @error('municipio_id') is-invalid @enderror">
                                <option value="">Seleccione…</option>
                                @foreach ($municipios as $m)
                                    <option value="{{ $m->id }}" @selected(old('municipio_id', $utilizador->municipio_id) == $m->id)>{{ $m->nome }}</option>
                                @endforeach
                            </select>
                            <x-campo-erro campo="municipio_id" />
                        </div>

                        <div data-ambito-bloco="escola">
                            <label class="form-label obrigatorio">Escolas atribuídas</label>
                            <input type="search" class="form-control form-control-sm mb-2" placeholder="Filtrar escolas…" id="filtroEscolas">
                            <div class="border rounded p-2 @error('escolas') border-danger @enderror" style="max-height: 260px; overflow-y: auto">
                                @foreach ($escolas as $e)
                                    <div class="form-check" data-escola-nome="{{ \Illuminate\Support\Str::lower($e->nome.' '.$e->municipio->nome) }}">
                                        <input class="form-check-input" type="checkbox" name="escolas[]" value="{{ $e->id }}" id="escola{{ $e->id }}" @checked(in_array($e->id, $escolasSeleccionadas, true))>
                                        <label class="form-check-label small" for="escola{{ $e->id }}">{{ $e->nome }} <span class="text-secondary">· {{ $e->municipio->nome }}</span></label>
                                    </div>
                                @endforeach
                            </div>
                            <x-campo-erro campo="escolas" />
                            <div class="form-text">Um professor pode estar atribuído a mais do que uma escola.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('utilizadores.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editar ? 'Guardar alterações' : 'Criar utilizador' }}</button>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var perfil = document.getElementById('perfil_id');
                var textos = {
                    global: 'Acesso a todos os municípios e escolas, com permissão de configuração.',
                    provincia: 'Consulta de toda a província. Não altera registos operacionais das escolas.',
                    municipio: 'Consulta limitada às escolas do município seleccionado.',
                    escola: 'Acesso limitado às escolas atribuídas.'
                };
                function actualizar() {
                    var op = perfil.options[perfil.selectedIndex];
                    var ambito = op ? op.dataset.ambito : null;
                    document.querySelectorAll('[data-ambito-bloco]').forEach(function (b) {
                        b.style.display = b.dataset.ambitoBloco === ambito ? '' : 'none';
                    });
                    document.getElementById('descricaoAmbito').textContent = ambito ? textos[ambito] : '';
                }
                perfil.addEventListener('change', actualizar);
                actualizar();
                document.getElementById('filtroEscolas').addEventListener('input', function () {
                    var t = this.value.toLowerCase();
                    document.querySelectorAll('[data-escola-nome]').forEach(function (el) {
                        el.style.display = el.dataset.escolaNome.indexOf(t) >= 0 ? '' : 'none';
                    });
                });
            })();
        </script>
    @endpush
</x-app-layout>
